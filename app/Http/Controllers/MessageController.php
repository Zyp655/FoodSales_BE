<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Events\NewChatMessage;
use App\Http\Requests\StoreMessageRequest; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    
    public function index(Request $request, Conversation $conversation)
    {
        
        $this->authorizeParticipant($conversation);

        $pageSize = $request->input('page_size', 20);

        $messages = $conversation->messages()
            ->with('sender') 
            ->latest() 
            ->simplePaginate($pageSize);

        return response()->json($messages);
    }

    
    public function store(StoreMessageRequest $request, Conversation $conversation)
    {
        
        $user = Auth::user();

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'sender_type' => $user->getMorphClass(),
            'body' => $request->body,
        ]);

        $message->load('sender'); 

        broadcast(new NewChatMessage($message))->toOthers();

        return response()->json($message, 201); 
    }

    private function authorizeParticipant(Conversation $conversation)
    {
        $user = Auth::user();
        
        $isParticipant = $conversation->participants()
                            ->where('participant_type', $user->getMorphClass())
                            ->where('participant_id', $user->id)
                            ->exists();

        if (!$isParticipant) {
            abort(403, 'This action is unauthorized.');
        }
    }
}