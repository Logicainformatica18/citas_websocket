<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImportMapping extends Model
{
    use HasFactory;

    protected $fillable = ['import_job_id', 'mapping'];
    protected $casts = ['mapping' => 'array'];

    public function job()
    {
        return $this->belongsTo(ImportJob::class);
    }
}
