<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat-room', function ($user) {
    return [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->firstname . ' ' . $user->lastname,
        'role_name' => $user->getRoleNames()->first(),
    ];
});


 