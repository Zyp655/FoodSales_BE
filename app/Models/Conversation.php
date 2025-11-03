<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['channel_name'];

    
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    
    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latest('created_at');
    }
}
