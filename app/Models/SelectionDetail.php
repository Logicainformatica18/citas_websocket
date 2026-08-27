<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectionDetail extends Model
{
    protected $fillable = [
        'description',
        'detail',
        'selection_id',
        'associate_detail_id',
    ];

    public function selection()
    {
        return $this->belongsTo(Selection::class);
    }

    public function associateDetail()
    {
        return $this->belongsTo(SelectionDetail::class, 'associate_detail_id');
    }

    public function associateDetails()
    {
        return $this->hasMany(SelectionDetail::class, 'associate_detail_id');
    }
}
