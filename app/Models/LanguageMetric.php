<?php
// app/Models/LanguageMetric.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LanguageMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id',
        'jobs_found_count',
        'jobs_new_count',
        'countries_breakdown',
        'modality_breakdown',
        'run_date',
        'source',
    ];

    protected $casts = [
        'countries_breakdown' => 'array',
        'modality_breakdown' => 'array',
        'run_date' => 'datetime',
    ];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
