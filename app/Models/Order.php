<?php

namespace App\Models;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    
    const STATUS_PENDING = 'Pending'; 
    const STATUS_PROCESSING = 'Processing'; 
    const STATUS_READY_FOR_PICKUP = 'ReadyForPickup'; 
    const STATUS_PICKING_UP = 'Picking Up';     
    const STATUS_IN_TRANSIT = 'In Transit'; 
    const STATUS_DELIVERED = 'Delivered'; 
    const STATUS_CANCELLED = 'Cancelled'; 
    
    const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_PICKING_UP,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'order'; 

    protected $fillable = [
        'user_id',
        'seller_id',
        'delivery_person_id',
        'total_amount',
        'status', 
        'delivery_address',
        'commission_amount',
        'distance_km', 
    ];
    
    protected $casts = [
        'user_id' => 'integer',
        'seller_id' => 'integer',
        'delivery_person_id' => 'integer',
        'total_amount' => 'float', 
        'commission_amount' => 'float', 
        'distance_km' => 'float', 
    ];

    public function user()
    {
        return $this->belongsTo(User::class); 
    }

    public function deliveryPerson()
    {
        return $this->belongsTo(User::class, 'delivery_person_id');
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