<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'compare_at_price',
        'is_active',
        'is_featured',
        'published_at',
        'sort_order',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
        'stock' => 'integer',
    ];

    /**
     * Resolved stock availability.
     *
     * Without variants: product.stock.
     * With variants: sum of active variant stock.
     */
    public function getInStockAttribute(): bool
    {
        if ($this->variants->isEmpty()) {
            return $this->stock > 0;
        }

        return $this->variants
            ->where('is_active', true)
            ->sum('stock') > 0;
    }

    /**
     * Categories
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'product_categories'
        )->withTimestamps();
    }

    /**
     * Product attributes
     */
    public function attributeValues(): BelongsToMany
{
    return $this->belongsToMany(
        AttributeValue::class,
        'product_attribute_values'
    )
    ->withPivot('attribute_id')
    ->withTimestamps();
}

    /**
     * Scalar values for number, boolean, and text attributes.
     */
    public function customAttributeValues(): HasMany
    {
        return $this->hasMany(ProductCustomAttributeValue::class);
    }

    /**
     * Product variants
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * Product images
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }

    /**
     * Primary image
     */
    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->where('is_primary', true)
            ->orderBy('sort_order');
    }

    /**
     * Compatible vehicle engines
     */
    public function vehicleEngines(): BelongsToMany
    {
        return $this->belongsToMany(
            VehicleEngine::class,
            'product_vehicle_compat'
        )->withTimestamps();
    }

    /**
     * Articles related to this product
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_product')->withTimestamps();
    }

    /**
     * Comments on this product
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
