<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SemanticContext extends Model
{
    protected $fillable = ['search_context', 'role_name', 'keyword_pattern', 'description'];

    public function technologies() {
        return $this->hasMany(Technology::class, 'context_id');
    }

    public function languages() {
        return $this->hasMany(Language::class, 'context_id');
    }

    public function methodologies() {
        return $this->hasMany(Methodology::class, 'context_id');
    }
}
