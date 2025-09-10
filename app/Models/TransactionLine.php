<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'process_date',
        'value_date',
        'description',
        'location',
        'branch_code',
        'operation_number',
        'time',
        'origin',
        'transaction_type',
        'debit',
        'credit',
        'balance',
    ];

    protected $casts = [
        'debit'   => 'decimal:2',
        'credit'  => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Relación con transacción padre.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
