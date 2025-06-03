<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'description',
        'icon',
        'order',
        'is_active'
    ];

    protected $casts = [
        'order' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id', 'category_id');
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($category) {
            if ($category->products()->count() > 0) {
                throw new \Exception('Cannot delete category with associated products');
            }
        });
    }
}