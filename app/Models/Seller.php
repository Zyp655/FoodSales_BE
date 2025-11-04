<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\MorphToMany;


class Seller extends Authenticatable
{
    use HasFactory,HasApiTokens;

    protected $table = 'sellers'; 

    protected $fillable = [
        'name',
        'email',
        'password', 
        'image',
        'address',
        'description',
        'role',
        'phone',
    ];
    
    protected $hidden = [
        'password',
    ];

    
    public function products()
    {
        return $this->hasMany(Product::class);
    }
    
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }


    public function broadcastAs(): string
    {
        return "seller-{$this->id}";
    }
    public function conversations(): MorphToMany
    {
        return $this->morphToMany(
            Conversation::class,
            'participant',
            'chat_participants'
        );
    }
}