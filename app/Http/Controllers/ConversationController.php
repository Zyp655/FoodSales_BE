<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Seller;
use App\Models\Order;
use App\Models\ChatParticipant;
use Illuminate\Database\Eloquent\Builder;

class ConversationController extends Controller
{
   
    public function index()
    {
        $user = Auth::user();

        $conversations = $user->conversations()
            ->with([
                'participants.participant' => function ($query) use ($user) {
                    $query->where('id', '!=', $user->id);
                },
                'lastMessage.sender' 
            ])
            ->get();
            
        return response()->json($conversations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|integer',
            'receiver_type' => 'required|string|in:user,seller,admin,delivery',
        ]);

        $sender = Auth::user();
        
        $senderType = 'user';
        if ($sender instanceof Seller) {
            $senderType = 'seller';
        } elseif ($sender->role === 'admin') {
            $senderType = 'admin';
        } elseif ($sender->role === 'delivery') {
            $senderType = 'delivery';
        }
        $senderId = $sender->id;

        $receiver = null;
        if ($validated['receiver_type'] === 'user' || $validated['receiver_type'] === 'admin' || $validated['receiver_type'] === 'delivery') {
            $receiver = User::find($validated['receiver_id']);
        } else if ($validated['receiver_type'] === 'seller') {
            $receiver = Seller::find($validated['receiver_id']);
        }

        if (!$receiver) {
            return response()->json(['message' => 'Receiver not found'], 404);
        }
        $receiverType = $validated['receiver_type'];
        $receiverId = $receiver->id;

        $canChat = false;

        if ($senderType === 'admin' || $receiverType === 'admin') {
            $canChat = true;
        }

        if (!$canChat) {
            $p1_type = $senderType;
            $p1_id = $senderId;
            $p2_type = $receiverType;
            $p2_id = $receiverId;

            if (($p2_type === 'user' && $p1_type !== 'user') || ($p2_type === 'seller' && $p1_type === 'delivery')) {
                [$p1_type, $p1_id, $p2_type, $p2_id] = [$p2_type, $p2_id, $p1_type, $p1_id];
            }
            
            $query = Order::query();

            if ($p1_type === 'user' && $p2_type === 'seller') {
                $query->where('user_id', $p1_id)->where('seller_id', $p2_id);
            } 
            elseif ($p1_type === 'user' && $p2_type === 'delivery') {
                $query->where('user_id', $p1_id)->where('delivery_person_id', $p2_id);
            }
            elseif ($p1_type === 'seller' && $p2_type === 'delivery') {
                $query->where('seller_id', $p1_id)->where('delivery_person_id', $p2_id);
            }
            else {
                $query->whereRaw('1=0'); 
            }
            
            if ($query->exists()) {
                 $canChat = true;
            }
        }

        if (!$canChat) {
            return response()->json(['message' => 'Not authorized to start this chat.'], 403);
        }

        $senderConversations = $sender->conversations()->pluck('conversations.id');
        $receiverConversations = $receiver->conversations()->pluck('conversations.id');
       

        $existingConversationId = $senderConversations->intersect($receiverConversations)->first();

        if ($existingConversationId) {
            $conversation = Conversation::find($existingConversationId);
            return response()->json($conversation);
        }

        $conversation = Conversation::create();

        $conversation->participants()->create([
            'participant_type' => $sender->getMorphClass(),
            'participant_id' => $sender->id,
        ]);

        $conversation->participants()->create([
            'participant_type' => $receiver->getMorphClass(),
            'participant_id' => $receiver->id,
        ]);

        return response()->json($conversation, 201); 
    }
}
