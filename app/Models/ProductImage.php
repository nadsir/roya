<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variant_id',
        'path',
        'alt_text',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variant
     *
     * Nullable because an image can belong
     * to the whole product instead of a specific variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}