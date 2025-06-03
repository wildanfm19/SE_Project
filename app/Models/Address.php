<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;
    protected $table = 'addresses';
    protected $primaryKey = 'address_id';
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'district_id',
        'poscode_id',
        'phone_number',
        'is_main',
        'biteship_id'
    ];
}
