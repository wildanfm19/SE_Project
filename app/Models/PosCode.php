<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCode extends Model
{
    use HasFactory;

    protected $table = 'pos_codes';
    protected $primaryKey = 'poscode_id';
    
    protected $fillable = [
        'code',
       
    ];

    // Relasi dengan Address
    public function addresses()
    {
        return $this->hasMany(Address::class, 'poscode_id', 'poscode_id');
    }

   
} 