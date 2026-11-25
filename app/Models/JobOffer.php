<?php

namespace App\Models;
use App\Models\MarketEntity;
use App\Services\AiClassification\OfferClassifier;
use App\Services\AiClassification\TitleKey;
use App\Services\Indicators\IndicatorScope;
use Illuminate\Support\Facades\DB;
use App\Services\JobOffers\TitleExclusionMatcher;
use App\Services\JobOffers\TitleExclusionStats;
use App\Support\Eloquent\SavedParentBelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Models\Certification;
class JobOffer extends Model
{
    use HasFactory;

    protected $table = 'job_offers';

    /**
     * Exclusión por título (roles que no son de TI, lista en job_title_exclusion_keywords):
     *   - creating: una oferta NUEVA cuyo título coincide con una exclusión activa NO se guarda
     *     (save() devuelve false; lo cuenta TitleExclusionStats y se registra al terminar el scraper).
     *   - updating: si cambia el título de una oferta existente, se recalcula title_exclusion_id.
     * Las ofertas existentes se marcan con php artisan job-offers:apply-title-exclusions.
     */
    protected static function booted(): void
    {
        static::creating(function (JobOffer $offer) {
            $match = TitleExclusionMatcher::active()->excludedBy($offer->title);
            if ($match !== null) {
                TitleExclusionStats::record($offer->source, $match, $offer->title);

                return false;
            }
            if (self::hasTiStatusColumn()) {
                $offer->title_key = TitleKey::key($offer->title) ?: OfferClassifier::EMPTY_KEY;
                $offer->inheritTitleClassification();
            }
        });

        static::updating(function (JobOffer $offer) {
            if ($offer->isDirty('title') && self::hasTitleExclusionColumn()) {
                $offer->title_exclusion_id = TitleExclusionMatcher::active()->excludedId($offer->title);
            }
            // Estado único: si cambia el título, la keyword decide y, si no, la oferta vuelve a clasificarse
            // (salvo decisión manual)
            if ($offer->isDirty('title') && self::hasTiStatusColumn() && $offer->ti_origin !== 'manual') {
                $offer->title_key = TitleKey::key($offer->title) ?: OfferClassifier::EMPTY_KEY;
                if ($offer->title_exclusion_id !== null) {
                    [$offer->ti_status, $offer->ti_origin, $offer->ti_result_id] = ['no_ti', 'keyword', null];
                } else {
                    [$offer->ti_status, $offer->ti_origin, $offer->ti_result_id] = ['pendiente', null, null];
                    $offer->inheritTitleClassification();
                }
            }
        });
    }

    /**
     * Clasificación con IA: si el título ya tiene decisión (ai_title_classifications) la oferta la hereda sin
     * llamada (decisión 3: lo no TI se guarda marcado), con el mismo criterio que OfferClassifier::inherit().
     */
    public function inheritTitleClassification(): void
    {
        static $hasTable = null;
        $hasTable ??= Schema::hasTable('ai_title_classifications');
        if (! $hasTable || $this->title_key === null || TitleKey::isGeneric((string) $this->title)) {
            return;
        }
        $tc = DB::table('ai_title_classifications')->where('title_key', $this->title_key)->first(['status', 'depends_on_description', 'origin', 'result_id']);
        if (! $tc) {
            return;
        }
        $text = trim(strip_tags((string) ($this->functions ?: ($this->description ?: $this->requirements))));
        $paren = str_contains((string) $this->title, '(') || str_contains((string) $this->title, '[');
        $noText = mb_strlen($text) < (int) config('ai_classification.min_description', 50) && ! $paren;
        if (($tc->status !== 'dudosa' && ! $tc->depends_on_description) || $noText) {
            $this->ti_status = $tc->status;
            $this->ti_origin = $tc->origin;
            $this->ti_result_id = $tc->result_id;
        }
    }

    public static function hasTiStatusColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('job_offers', 'ti_status');
    }

    /**
     * Ofertas que cuentan en los indicadores de análisis (sin las excluidas por título).
     * Criterio común: App\Services\Indicators\IndicatorScope.
     */
    public function scopeForIndicators(Builder $query): Builder
    {
        IndicatorScope::offers($query->getQuery(), $query->getModel()->getTable());

        return $query;
    }

    public static function hasTitleExclusionColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('job_offers', 'title_exclusion_id');
    }

    /** Las relaciones muchos a muchos no escriben el pivote si la oferta no se guardó (ver booted()). */
    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return new SavedParentBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
    }

    /** Patrón que excluyó la oferta por su título (null = válida). */
    public function titleExclusion()
    {
        return $this->belongsTo(JobTitleExclusionKeyword::class, 'title_exclusion_id');
    }

    /**
     * 🧩 Campos que se pueden asignar masivamente
     */
    protected $fillable = [
        'title',
        'company',
        'country',
        'region',
        'state_code',
        'city',
        'latitude',
        'longitude',
        'modality',
        'workload',
        'experience_level',
        'education_level',   // 🆕 Nivel educativo requerido (Bachelor, Master, etc.)
        'certifications',    // 🆕 Certificados detectados (AWS, Scrum, ITIL...)
        'requirements',      // 🆕 Texto con requisitos extraídos
        'skills',            // 🆕 Habilidades clave (Python, React, SQL...)
        'description',
        'benefits',
        'functions',          // 🆕 Funciones del puesto (texto plano, App\Support\HtmlText)
        'functions_headline', // 🆕 Título de la sección de funciones en la fuente
        'functions_origin',   // 🆕 'field' (campo propio de la fuente) | 'heuristic' (extraído de la descripción)
        'salary_min',
        'salary_max',
        'currency',
        'compensation_type',
        'source',
        'external_id',
        'url',
        'search_query',
        'published_at',
    ];

    /**
     * 🧠 Conversión automática de tipos
     */
    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'salary_min'   => 'decimal:2',
        'salary_max'   => 'decimal:2',
        'published_at' => 'datetime',
    ];
public function marketEntities()
{
    return $this->belongsToMany(
        MarketEntity::class,
        'job_offer_market_entity', // pivot
        'job_offer_id',
        'market_entity_id'
    );
}
    /**
     * 📊 Accesor opcional: retorna skills como array
     */
    public function getSkillsArrayAttribute(): array
    {
        if (empty($this->skills)) return [];
        return array_map('trim', explode(',', $this->skills));
    }

    /**
     * 📊 Accesor opcional: retorna certificaciones como array
     */
    public function getCertificationsArrayAttribute(): array
    {
        if (empty($this->certifications)) return [];
        return array_map('trim', explode(',', $this->certifications));
    }
    public function languages()
{
    return $this->belongsToMany(Language::class, 'language_job')
                ->withTimestamps();
}
    /**
     * 🔹 Relación con tecnologías
     */
 public function technologies()
{
    return $this->belongsToMany(
        Technology::class,
        'technology_job',
        'job_offer_id',
        'technology_id'
    )
    ->withPivot('market_entity_id')
    ->withTimestamps();
}
    /**
     * 🔹 Relación con metodologías
     */
    public function methodologies()
    {
        return $this->belongsToMany(Methodology::class, 'methodology_job')
                    ->withTimestamps();
    }
public function competencies()
{
    return $this->belongsToMany(Competency::class, 'competency_job_offer')
                ->withTimestamps();
}
public function analyzeCompetenciesWeighted(int $careerId): array
{
    $texto = strtolower(
        ($this->title ?? '') . ' ' .
        ($this->requirements ?? '') . ' ' .
        ($this->skills ?? '') . ' ' .
        ($this->description ?? '')
    );

    $competencias = Competency::where('career_id', $careerId)->get();

    $matched = [];
    $missing = [];
    $score = 0;
    $totalWeight = $competencias->sum('weight');

    foreach ($competencias as $comp)
    {
        $match = str_contains($texto, strtolower($comp->name))
              || str_contains($texto, strtolower($comp->category))
              || str_contains($texto, strtolower($comp->description_es));

        if ($match) {
            $matched[] = $comp->name;
            $score += $comp->weight;
        } else {
            $missing[] = $comp->name;
        }
    }

    $finalScore = $totalWeight > 0 ? $score / $totalWeight : 0;

    return [
        'matched' => $matched,
        'missing' => $missing,
        'score'   => round($finalScore, 4),
    ];
}

/**
 * 🏅 Certificaciones asociadas a la oferta laboral
 */
public function certifications()
{
    return $this->belongsToMany(
        Certification::class,
        'certification_job',
        'job_offer_id',
        'certification_id'
    )->withTimestamps();
}
public function trendLinks()
{
    return $this->hasMany(
        TechnologyTrendJob::class,
        'job_offer_id'
    );
}
// App\Models\JobOffer.php

public function marketCertifications()
{
    return $this->belongsToMany(
        MarketEntity::class,
        'certification_job',     // 👈 tabla real
        'job_offer_id',          // FK actual
        'market_entity_id'       // FK a market_entities
    )
    ->withTimestamps();
}

}
