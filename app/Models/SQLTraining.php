<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SQLTraining extends Model
{
    protected $table = 'sqltrainings';
    protected $fillable = [
        'query_text', 'sql_generated', 'sql_validated',
        'last_test_output', 'test_status', 'test_message', 'created_by'
    ];
}
