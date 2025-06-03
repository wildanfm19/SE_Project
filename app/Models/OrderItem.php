<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $primaryKey = 'order_item_id';
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'total_price',
        'subtotal'
    ];

    // Tambahkan relasi ke Review
    public function review()
    {
        return $this->hasOne(Review::class, 'order_item_id', 'order_item_id');
    }

    // Relasi ke order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    // Relasi ke product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
} 