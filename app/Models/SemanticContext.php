<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SemanticContext extends Model
{
    use HasFactory;

    protected $table = 'semantic_contexts';

    protected $fillable = [
        'search_context',


        'description',
    ];

    /**
     * 🌐 Relaciones con otras entidades semánticas
     */
    public function technologies()
    {
        return $this->hasMany(Technology::class, 'context_id');
    }

    public function languages()
    {
        return $this->hasMany(Language::class, 'context_id');
    }

    public function methodologies()
    {
        return $this->hasMany(Methodology::class, 'context_id');
    }
}
