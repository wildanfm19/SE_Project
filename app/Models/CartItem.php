<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $primaryKey = 'cart_item_id';
    
    // Menentukan kolom yang bisa di-assign secara massal
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'price',
        'created_at',
        'updated_at'
    ];

    // Kolom yang harus di-cast ke tipe data tertentu
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }
} 