<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function ancestors()
{
    $ancestors = collect();

    $category = $this->parent;

    while ($category) {
        $ancestors->prepend($category);
        $category = $category->parent;
    }

    return $ancestors;
}

    /**
     * Child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order');
    }

    /**
     * Products belonging to this category
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_categories'
        )->withTimestamps();
    }

    /**
     * Attributes/filters assigned to this category
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'category_attributes'
        )
        ->withPivot([
            'is_required',
            'sort_order',
        ])
        ->withTimestamps()
        ->orderByPivot('sort_order');
    }
}