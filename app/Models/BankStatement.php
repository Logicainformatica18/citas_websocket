<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankStatement extends Model
{
    protected $table = 'bank_statements';

    protected $fillable = [
        'date_str',
        'union_name',
        'code',
        'code_abbr',
        'project',
        'stage',
        'lot',
        'amount',
        'operation_number',
        'operation_time',
        'comments',
        'paid_by',
        'account_number',
        'file_name',
        'amount_comment',
    ];
}
