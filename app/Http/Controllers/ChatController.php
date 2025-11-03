<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\NewChatMessage;
use App\Models\User;
use App\Models\Seller;
use App\Models\Conversation; 
use App\Models\Message;     

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
        $messageText = $request->message;
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

        $senderIdentifier = "{$senderType}-{$sender->id}";
        $receiverIdentifier = "{$receiverType}-{$receiverId}";

        $participants = [$senderIdentifier, $receiverIdentifier];
        sort($participants); 
        
        $channelName = 'private-chat.' . $participants[0] . '.' . $participants[1];

        $conversation = Conversation::firstOrCreate(
            ['channel_name' => $channelName]
        );

        $conversation->messages()->create([
            'sender_id' => $sender->id,
            'sender_type' => $senderType,
            'body' => $messageText, 
        ]);

        broadcast(new NewChatMessage($channelName, $senderType, $sender->id, $messageText))->toOthers();

        return response()->json([
            'success' => 1,
            'message' => 'Message sent',
            'channel_name' => $channelName,
        ]);
    }
}
