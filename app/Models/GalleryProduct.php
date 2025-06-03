<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class GalleryProduct extends Model
{
    use HasFactory;

    protected $table = 'gallery_products';
    protected $primaryKey = 'gallery_id';

    protected $fillable = [
        'product_id',
        'seller_id',
        'image_url',
        'is_main',
        'uploaded_at'
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $appends = ['image_url'];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'seller_id');
    }

    // Scopes
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    public function scopeAdditional($query)
    {
        return $query->where('is_main', false);
    }

    // Accessors & Mutators
    public function getImageUrlAttribute()
    {
        return $this->attributes['image_url'] ? config('app.url') . $this->attributes['image_url'] : null;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        // Sebelum menyimpan
        static::saving(function ($gallery) {
            // Jika ini gambar utama, pastikan tidak ada gambar utama lain
            if ($gallery->is_main) {
                static::where('product_id', $gallery->product_id)
                      ->where('gallery_id', '!=', $gallery->gallery_id)
                      ->update(['is_main' => false]);
            }
        });

        // Sebelum menghapus
        static::deleting(function ($gallery) {
            if ($gallery->is_main) {
                throw new \Exception('Cannot delete main image. Set another image as main first.');
            }
        });

        // Setelah menghapus
        static::deleted(function ($gallery) {
            $path = str_replace('/storage/', '', $gallery->getRawOriginal('image_url'));
            Storage::disk('public')->delete($path);
        });
    }
}
