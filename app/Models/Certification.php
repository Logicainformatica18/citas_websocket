<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certification extends Model
{
    use HasFactory;

    protected $table = 'certifications';

    protected $fillable = [
        'name',
        'slug',
        'vendor',        // AWS, Microsoft, Google, PMI, etc.
        'level',         // associate, professional, expert, foundation, etc.
        'enabled',
        'market_entity_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /* =========================================================
       Relationships
    ========================================================= */

    /**
     * Ofertas laborales que mencionan esta certificación
     */
    public function jobOffers()
    {
        return $this->belongsToMany(
            JobOffer::class,
            'certification_job',
            'certification_id',
            'job_offer_id'
        )->withTimestamps();
    }

    /* =========================================================
       Scopes
    ========================================================= */

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Certificaciones que deben buscar los scrapers: habilitadas y con su entidad activa
     * (market_entities.is_active es la fuente de verdad, igual que en Language y Technology).
     */
    public function scopeActive($query)
    {
        return $query
            ->where('certifications.enabled', true)
            ->where(function ($q) {
                $q->whereNull('certifications.market_entity_id')
                    ->orWhereExists(function ($e) {
                        $e->selectRaw('1')
                            ->from('market_entities as me_active')
                            ->whereColumn('me_active.id', 'certifications.market_entity_id')
                            ->where('me_active.is_active', 1);
                    });
            });
    }

    public function marketEntity()
    {
        return $this->belongsTo(MarketEntity::class, 'market_entity_id');
    }

    /* =========================================================
       Helpers
    ========================================================= */

    /**
     * Normaliza nombres tipo:
     *  - AWS Certified Solutions Architect
     *  - AWS Solutions Architect Associate
     */
    public static function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper($name)));
    }
    public function courses()
{
    return $this->belongsToMany(Course::class, 'certification_course')
        ->withPivot('relevance_level', 'weight')
        ->withTimestamps();
}

}
