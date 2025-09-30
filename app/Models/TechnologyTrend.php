<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnologyTrend extends Model
{
  protected $fillable = [
    'num_pushers',
    'language',
    'language_type',
    'iso2_code',
    'year',
    'quarter',
];

}
