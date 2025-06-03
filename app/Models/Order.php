<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $primaryKey = 'order_id';
    protected $fillable = [
        'user_id',
        'seller_id',
        'address_id',
        'total_amount',
        'status',
        'shipping_status',
        'payment_type',
        'transaction_id',
        'snap_token',
        'paid_at',
        'payment_url'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'paid_at'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCEL = 'cancel';
    const STATUS_CHALLENGE = 'challenge';

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function paymentNotifications()
    {
        return $this->hasMany(PaymentNotification::class, 'order_id', 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Relasi ke order items
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
} 