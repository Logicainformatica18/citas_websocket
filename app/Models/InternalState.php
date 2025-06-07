<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalState extends Model
{
    protected $table = 'internal_states';

    protected $fillable = [
        'description',
        'detail',
    ];
}
