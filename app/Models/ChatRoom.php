<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChatRoom extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'room_id';
    
    protected $fillable = [
        'customer_id',
        'seller_id',
        'last_message',
        'last_message_time',
        'is_active',
    ];

    protected $casts = [
        'last_message_time' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'seller_id');
    }

    public function messages()
    {
        return $this->hasMany(Chat::class, 'room_id', 'room_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(Chat::class, 'room_id', 'room_id')
            ->latest();
    }
} 