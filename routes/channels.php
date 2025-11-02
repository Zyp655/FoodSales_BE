<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Seller;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.Seller.{id}', function ($user, $id) {
    return $user instanceof Seller && (int) $user->id === (int) $id;
});

Broadcast::channel('private-chat.{channelName}', function ($user, $channelName) {
    
    $userType = 'user';
    if ($user instanceof Seller) {
        $userType = 'seller';
    } elseif ($user->role === 'admin') {
        $userType = 'admin';
    } elseif ($user->role === 'delivery') {
        $userType = 'delivery';
    }
    $userId = $user->id;
    
    $currentUserIdentifier = "{$userType}-{$userId}";

    $participants = explode('.', $channelName);

    if (in_array($currentUserIdentifier, $participants)) {
        return true; 
    }

    return false;
});