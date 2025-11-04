<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatParticipant; 



Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    
    return ChatParticipant::where('conversation_id', $conversationId)
                           ->where('participant_type', $user->getMorphClass()) 
                           ->where('participant_id', $user->id)
                           ->exists();
});