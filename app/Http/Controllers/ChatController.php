<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NewChatMessage;
use App\Models\User;
use App\Models\Seller;

class ChatController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_type' => 'required|string|in:user,seller,admin,delivery',
            'receiver_id' => 'required|integer',
            'message' => 'required|string|max:1000',
        ]);

        $sender = Auth::user();
        $message = $request->message;

        $receiverType = $request->receiver_type;
        $receiverId = $request->receiver_id;
        
        $senderType = 'user';
        if ($sender instanceof Seller) {
            $senderType = 'seller';
        } elseif ($sender->role === 'admin') {
            $senderType = 'admin';
        } elseif ($sender->role === 'delivery') {
            $senderType = 'delivery';
        }

        $participants = [
            ['type' => $senderType, 'id' => $sender->id],
            ['type' => $receiverType, 'id' => $receiverId],
        ];

        sort($participants); 
        $channelName = 'private-chat.' . $participants[0]['type'] . '-' . $participants[0]['id'] . '.' . $participants[1]['type'] . '-' . $participants[1]['id'];

        broadcast(new NewChatMessage($channelName, $senderType, $sender->id, $message))->toOthers();

        return response()->json([
            'success' => 1,
            'message' => 'Message sent',
            'channel_name' => $channelName,
        ]);
    }
}