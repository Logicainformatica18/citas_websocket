<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalState extends Model
{
    protected $table = 'external_states';

    protected $fillable = [
        'description',
        'detail',
    ];
}
