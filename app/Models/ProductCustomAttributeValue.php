<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCustomAttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'attribute_id',
        'value_type',
        'value_number',
        'value_boolean',
        'value_text',
    ];

    protected $casts = [
        'value_number' => 'decimal:4',
        'value_boolean' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function typedValue(): bool|float|string|null
    {
        return match ($this->value_type) {
            'number' => $this->value_number !== null
                ? (float) $this->value_number
                : null,
            'boolean' => $this->value_boolean,
            'text' => $this->value_text,
            default => null,
        };
    }
}
