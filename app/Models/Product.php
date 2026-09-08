<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

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
     * Product variants
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
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
}