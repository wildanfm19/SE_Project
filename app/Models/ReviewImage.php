<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReviewImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'image_path',
        'is_main'
    ];

    protected $casts = [
        'is_main' => 'boolean'
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
} 