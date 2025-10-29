<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

  
    protected $table = 'order_item';
    
    
    protected $primaryKey = 'id';
    
   
    protected $fillable = [
        'order_id', 
        'product_id', 
        'quantity', 
        'price_at_purchase', 
    ];

   
    protected $casts = [
        'quantity' => 'integer',
        'price_at_purchase' => 'float',
    ];

    
    
     
    
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

   
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
