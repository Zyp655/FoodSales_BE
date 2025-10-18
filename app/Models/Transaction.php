<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;
    
    
    protected $table = 'transaction'; 


    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'payment_method', 
        'status', 
        'qr_data', 
    ];
    

    protected $casts = [
        'amount' => 'decimal:2', 
        'order_id' => 'integer',
        'user_id' => 'integer',
    ];


    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
   
}