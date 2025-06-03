<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $primaryKey = 'cart_id';
    protected $fillable = [
        'user_id',
        'created_at',
        'updated_at'
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'cart_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    
    public function getTotalAttribute()
    {
        return $this->items->sum(function($item) {
            return $item->price * $item->quantity;
        });
    }
} 