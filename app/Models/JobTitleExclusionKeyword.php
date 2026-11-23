<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Patrón de la lista de exclusión por título (ver App\Services\JobOffers\TitleExclusionMatcher).
 *   type        'exclude' (rol no TI) | 'exception' (protege títulos que coincidirían por error; gana)
 *   match_mode  'text' (literal) | 'regex'
 *   whole_word  exige palabra completa
 *   origin      'seed' (PATRONES_EXCLUIR del script) | 'manual' | 'ai' (sugerencia aprobada)
 *   matches_count / counted_at  ofertas cuyo título coincide (último job-offers:apply-title-exclusions)
 */
class JobTitleExclusionKeyword extends Model
{
    protected $table = 'job_title_exclusion_keywords';

    protected $fillable = [
        'pattern', 'type', 'match_mode', 'whole_word', 'category', 'is_active', 'origin', 'notes',
    ];

    protected $casts = [
        'whole_word' => 'boolean',
        'is_active' => 'boolean',
        'matches_count' => 'integer',
        'counted_at' => 'datetime',
    ];
}
