<?php

namespace App\Models;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'order'; 

    protected $fillable = [
        'user_id',
        'seller_id',
        'total_amount',
        'status', 
        'delivery_address',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class); 
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
    
    public function items()
    {
        return $this->hasMany(OrderItem::class); 
    }
}