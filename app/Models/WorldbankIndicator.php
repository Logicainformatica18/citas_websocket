<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorldbankIndicator extends Model
{
    use HasFactory;

    protected $table = 'worldbank_indicators';

    protected $fillable = [
        'country_code',
        'country_name',
        'indicator_code',
        'indicator_name',
        'year',
        'value',
        'source',
    ];

    /**
     * 🔹 Alcances útiles para filtrar indicadores
     */

    // Buscar por código de indicador
    public function scopeByIndicator($query, string $code)
    {
        return $query->where('indicator_code', $code);
    }

    // Buscar por país
    public function scopeByCountry($query, string $country)
    {
        return $query->where('country_code', strtoupper($country));
    }

    // Buscar por rango de años
    public function scopeBetweenYears($query, int $from, int $to)
    {
        return $query->whereBetween('year', [$from, $to]);
    }

    /**
     * 🔹 Getter formateado (por ejemplo para tus dashboards JSON)
     */
    public function getFormattedAttribute()
    {
        return [
            'country' => $this->country_name,
            'indicator' => $this->indicator_name,
            'year' => $this->year,
            'value' => $this->value,
        ];
    }
}
