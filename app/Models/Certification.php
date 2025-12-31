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
