<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    protected $table = 'product'; 
    public $timestamps = false;
    protected $fillable = [
        'seller_id',
        'name',
        'image',
        'price_per_kg',
        'description',
        'interaction_count',
        'category_id',
    ];

    protected $attributes = [
        'interaction_count' => 0,
    ];

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function scopeSearch($query, $searchQuery)
    {
        return $query->where('name', 'LIKE', '%' . $searchQuery . '%')
                     ->orWhere('description', 'LIKE', '%' . $searchQuery . '%');
    }
}