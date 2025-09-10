<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'file_path',
        'raw_text',
        'x',
        'y',
        'width',
        'height',
    ];

    /**
     * Relación con transacción padre.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
