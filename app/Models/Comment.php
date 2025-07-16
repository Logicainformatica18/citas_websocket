<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'support_detail_id',
        'comment',
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
