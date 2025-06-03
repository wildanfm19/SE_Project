<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BogorDistrict extends Model
{
    use HasFactory;

    protected $table = 'bogor_districts';

    protected $fillable = [
        'district_name',
        'postal_code',
        'is_active',
    ];

    // Relasi dengan ShippingAddress
    public function shippingAddresses()
    {
        return $this->hasMany(ShippingAddress::class);
    }
} 