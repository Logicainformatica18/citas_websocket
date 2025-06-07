<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Article;
class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'details',

        'sender_firstname',
        'sender_lastname',
        'sender_email',

        'receiver_firstname',
        'receiver_lastname',
        'receiver_email',

        'file_1',

        'confirmation_token',
        'confirmed_at',
        'received_at',
    ];

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

}
