<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_at_price',
        'stock',
        'is_active',
        'combination_key',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Attribute values of this variant
     *
     * Example:
     * Color: Black
     * Size: M
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'variant_id',
            'attribute_value_id'
        )->withTimestamps();
    }

    /**
     * Variant images
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)
            ->orderBy('sort_order');
    }
}