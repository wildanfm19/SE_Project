<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $primaryKey = 'district_id';
    
    protected $fillable = [
        'district_name'
    ];

    // Relasi dengan Address
    public function addresses()
    {
        return $this->hasMany(Address::class, 'district_id', 'district_id');
    }

   
} 