<?php

// app/Models/Seller.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Seller extends Model
{
    use HasFactory;
    protected $primaryKey = 'seller_id';

    protected $fillable = [
        'user_id', 'store_name', 'store_address', 'store_logo', 'store_description', 'store_rating', 'total_sales', 'phone_number', 'email'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chatRooms()
    {
        return $this->hasMany(ChatRoom::class, 'seller_id', 'seller_id');
    }

    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Chat::class, 'sender');
    }

    public function receivedMessages(): MorphMany
    {
        return $this->morphMany(Chat::class, 'receiver');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function getStoreLogoUrlAttribute()
    {
        return $this->store_logo ? Storage::url($this->store_logo) : null;
    }
}


