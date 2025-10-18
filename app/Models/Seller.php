<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Dùng cho Authentication/Login

class Seller extends Authenticatable
{
    use HasFactory;

    protected $table = 'sellers'; 

    protected $fillable = [
        'name',
        'email',
        'password', 
        'image',
        'address',
        'description',
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
}