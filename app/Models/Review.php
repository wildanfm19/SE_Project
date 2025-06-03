<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';
    protected $primaryKey = 'review_id';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'user_id',
        'product_id',
        'seller_id',
        'rating',
        'comment'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'order_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'seller_id');
    }

    public static function updateSellerRating($sellerId)
    {
        $averageRating = self::where('seller_id', $sellerId)
            ->select(\DB::raw('ROUND(AVG(rating), 2) as avg_rating'))
            ->first();

        return \DB::table('sellers')
            ->where('seller_id', $sellerId)
            ->update([
                'store_rating' => $averageRating->avg_rating ?? 0,
                'updated_at' => now()
            ]);
    }
}
