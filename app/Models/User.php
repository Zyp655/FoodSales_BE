<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users'; 

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'address',
        'lat',
        'lng',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token', 
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', 
        'lat' => 'float',
        'lng' => 'float',
    ];
    
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isDeliveryPerson()
    {
        return $this->role === 'delivery';
    }

    public function cartItems()
    {
        return $this->hasMany(Cart::class);
    }
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
