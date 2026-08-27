<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Selection extends Model
{
    protected $fillable = [
        'description',
        'detail',
        'state',
        'associate_id',
    ];

    public function associate()
    {
        return $this->belongsTo(Selection::class, 'associate_id');
    }

    public function associates()
    {
        return $this->hasMany(Selection::class, 'associate_id');
    }

    public function details()
    {
        return $this->hasMany(SelectionDetail::class);
    }
}
