<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnologyCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * 🔁 Relación inversa: una categoría tiene muchas tecnologías
     */
    public function technologies()
    {
        return $this->hasMany(Technology::class, 'category_id');
    }
}
