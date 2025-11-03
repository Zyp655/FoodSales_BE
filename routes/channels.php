<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;
use App\Models\Seller;
use Illuminate\Support\Facades\Log; 

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.Seller.{id}', function ($user, $id) {
    return $user instanceof Seller && (int) $user->id === (int) $id;
});

Broadcast::channel('private-chat.{channelName}', function ($user, $channelName) {
    
    Log::info('--- YÊU CẦU XÁC THỰC KÊNH CHAT MỚI ---');
    Log::info('Kênh (Channel): ' . $channelName);
    
    if ($user) {
        Log::info('Người dùng (User) đã xác thực: ' . $user->name . ' (ID: ' . $user->id . ')');
        Log::info('Role của người dùng: ' . $user->role); 
    } else {
        Log::info('LỖI: Người dùng (User) không được xác thực (null).');
        return false;
    }

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
    
    Log::info('Identifier của người dùng: ' . $currentUserIdentifier);

    $participants = explode('.', $channelName);
    Log::info('Những người tham gia (Participants) trong kênh: ' . implode(', ', $participants));

    if (in_array($currentUserIdentifier, $participants)) {
        Log::info('KẾT QUẢ: CHO PHÉP (TRUE)');
        return true; 
    }

    Log::info('KẾT QUẢ: TỪ CHỐI (FALSE)');
    return false;
});
