<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    use HasFactory;

  protected $fillable = [
    'support_detail_id',
    'user_id',
    'comment',
    'internal_state_id', // ✅ Este debe estar aquí
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supportDetail()
    {
        return $this->belongsTo(SupportDetail::class);
    }
    // app/Models/Comment.php

public function internalState()
{
    return $this->belongsTo(InternalState::class, 'internal_state_id');
}

}
