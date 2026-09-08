<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_filterable',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_filterable' => 'boolean',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Categories using this attribute
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_attributes'
        )
        ->withPivot([
            'is_required',
            'sort_order',
        ])
        ->withTimestamps()
        ->orderByPivot('sort_order');
    }

    /**
     * Values belonging to this attribute
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)
            ->orderBy('sort_order');
    }
}