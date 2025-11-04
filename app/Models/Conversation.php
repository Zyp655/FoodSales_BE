<?php

namespace App\Models; 

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['channel_name'];


    public function participants()
    {
        
        return $this->hasMany(ChatParticipant::class);
    }

    
    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}