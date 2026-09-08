<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'label',
        'value',
        'hex_color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Attribute this value belongs to
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Products using this attribute value
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_attribute_values'
        )
        ->withTimestamps();
    }

    /**
     * Variants using this attribute value
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'variant_attribute_values',
            'attribute_value_id',
            'variant_id'
        )
        ->withTimestamps();
    }
}