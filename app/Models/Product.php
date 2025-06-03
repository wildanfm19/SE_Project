<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';
    protected $primaryKey = 'product_id';
    protected $guarded = [];

    protected $fillable = [
        'category_id',
        'seller_id',
        'product_name',
        'description',
        'price',
        'stock_quantity',
        'is_active',
        'average_rating',
        'total_reviews',
        'total_sales'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'is_active' => 'boolean',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $appends = ['average_rating', 'total_reviews'];

    public function getAverageRatingAttribute()
    {
        return floatval($this->attributes['average_rating'] ?? 0);
    }

    public function getTotalReviewsAttribute()
    {
        return intval($this->attributes['total_reviews'] ?? 0);
    }

    // Relationships
    public function carts()
    {
        return $this->hasMany(Cart::class, 'product_id', 'product_id');
    }
    
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'seller_id');
    }

    public function images()
    {
        return $this->hasMany(GalleryProduct::class, 'product_id', 'product_id');
    }

    public function mainImage()
    {
        return $this->hasOne(GalleryProduct::class, 'product_id', 'product_id')
                    ->where('is_main', true);
    }

    public function additionalImages()
    {
        return $this->hasMany(GalleryProduct::class, 'product_id', 'product_id')
                    ->where('is_main', false);
    }

    public function galleries()
    {
        return $this->hasMany(GalleryProduct::class, 'product_id', 'product_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'product_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        // Saat soft delete, non-aktifkan gambar
        static::deleting(function ($product) {
            $product->images()->update(['is_active' => false]);
        });

        // Saat restore, aktifkan kembali gambar
        static::restored(function ($product) {
            $product->images()->update(['is_active' => true]);
        });

        // Saat force delete, hapus gambar
        static::forceDeleting(function ($product) {
            $product->images()->delete();
        });
    }

    // Accessor untuk memastikan nama produk tidak null
    public function getNameAttribute($value)
    {
        return $value ?? 'Product Name';
    }

    // Accessor untuk memastikan harga dalam integer
    public function getPriceAttribute($value)
    {
        return (int) $value;
    }

    // Tambahkan accessor untuk main image
    public function getMainImageUrlAttribute()
    {
        return $this->mainImage ? $this->mainImage->image_url : null;
    }

    // Tambahkan method untuk update rating
    public static function updateProductRating($productId)
    {
        $product = self::find($productId);
        if ($product) {
            $avgRating = $product->reviews()->avg('rating') ?? 0;
            $totalReviews = $product->reviews()->count();
            
            $product->update([
                'average_rating' => round($avgRating, 2),
                'total_reviews' => $totalReviews
            ]);
        }
    }

    // Method untuk update total sales
    public function updateTotalSales($orderId)
    {
        $totalQuantity = $this->orderItems()
            ->whereHas('order', function($query) {
                $query->where('status', 'completed');
            })
            ->sum('quantity');
        
        $this->update([
            'total_sales' => $totalQuantity
        ]);
    }
}
