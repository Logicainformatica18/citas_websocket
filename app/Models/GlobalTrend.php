<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlobalTrend extends Model
{
    use HasFactory;

    protected $table = 'global_trends';

    /**
     * Campos asignables.
     */
    protected $fillable = [
        // Fuente
        'source',
        'source_url',
        'source_type',

        // Clasificación
        'category',
        'subcategory',

        // Identificador único real (GitHub, npm, API externa)
        'repo_node_id',

        // Información del ítem
        'item_name',
        'item_type',
        'summary',

        // Datos cuantitativos
        'year',
        'quarter',
        'value',
        'rank',
        'country',
        'region',

        // Estructura en JSON
        'metadata',

        // Compatibilidad histórica
        'hash',
    ];

    /**
     * Tipos nativos y casts JSON.
     */
    protected $casts = [
        'metadata' => 'array',
        'year'     => 'integer',
        'quarter'  => 'integer',
        'value'    => 'decimal:4',
        'rank'     => 'integer',
    ];


    /*============================================================
     | SCOPES DINÁMICOS PARA FILTROS
     *============================================================*/

    public function scopeSource($query, $source)
    {
        return $query->where('source', $source);
    }

    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeTopic($query, $topic)
    {
        return $query->where('subcategory', $topic);
    }

    public function scopeYear($query, $year)
    {
        return $query->where('year', $year);
    }

    public function scopeCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    public function scopeSearch($query, $text)
    {
        return $query->where('item_name', 'LIKE', "%{$text}%");
    }

    public function scopeTopN($query, $n = 10)
    {
        return $query->orderBy('rank')->limit($n);
    }

    public function scopeBetweenYears($query, $from, $to)
    {
        return $query->whereBetween('year', [$from, $to]);
    }

    public function scopeForDashboard($query, $category, $topic)
    {
        return $query->where('category', $category)
                     ->where('subcategory', $topic)
                     ->orderBy('rank');
    }


    /*============================================================
     | ACCESSORS ÚTILES PARA EL FRONT
     *============================================================*/

    public function getItemTypeLabelAttribute()
    {
        return match ($this->item_type) {
            'technology' => 'Tecnología',
            'skill'      => 'Habilidad',
            'competency' => 'Competencia',
            'metric'     => 'Métrica',
            'trend'      => 'Tendencia',
            'dataset'    => 'Dataset',
            default      => ucfirst($this->item_type),
        };
    }

    public function getFormattedValueAttribute()
    {
        return number_format($this->value, 0, '.', ',');
    }

    public function getPeriodLabelAttribute()
    {
        return "{$this->year}T{$this->quarter}";
    }


    /*============================================================
     | MÉTODO UNIVERSAL PARA UPSERT (INSERCIÓN + ACTUALIZACIÓN)
     *============================================================*/

    /**
     * Inserta o actualiza una tendencia según node_id/ topic / year.
     */
    public static function upsertTrend(array $data)
    {
        return self::updateOrCreate(
            [
                'repo_node_id' => $data['repo_node_id'] ?? null,
                'subcategory'  => $data['subcategory'] ?? null,
                'year'         => $data['year'] ?? now()->year,
            ],
            $data
        );
    }
}
