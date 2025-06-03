<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentNotification extends Model
{
    protected $fillable = [
        'order_id',
        'transaction_id',
        'status',
        'payment_type',
        'gross_amount',
        'raw_response'
    ];

    protected $casts = [
        'raw_response' => 'array',
        'gross_amount' => 'decimal:2'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
} 