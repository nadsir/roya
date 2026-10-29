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
        'meta_title',
        'meta_description',
        'canonical_url',
        'h1_title',
        'top_description',
        'bottom_content',
        'faqs',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'faqs' => 'array',
    ];

    /**
     * SEO title with a sensible fallback chain (explicit meta → name → site name).
     */
    public function getSeoTitleAttribute(): string
    {
        $title = trim((string) ($this->meta_title ?: ''));

        return $title !== '' ? $title : ($this->name ?: 'گالری رویا');
    }

    /**
     * SEO description with fallbacks and search-snippet length truncation.
     */
    public function getSeoDescriptionAttribute(): string
    {
        foreach ([$this->meta_description, $this->top_description, $this->description] as $candidate) {
            $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $candidate)) ?? '');
            if ($text !== '') {
                return mb_substr($text, 0, 160);
            }
        }

        return sprintf('خرید %s از گالری رویا؛ مجموعه‌ای از انتخاب‌های شیک با ارسال سریع.', $this->name);
    }

    /**
     * Display heading with fallback to the category name.
     */
    public function getH1TitleAttribute(): string
    {
        $title = trim((string) ($this->attributes['h1_title'] ?? ''));

        return $title !== '' ? $title : ($this->name ?? '');
    }

    /**
     * Normalized FAQ list: only entries with a question and an answer.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function getNormalizedFaqsAttribute(): array
    {
        return collect($this->faqs)
            ->filter(fn ($faq) => is_array($faq)
                && ! empty(trim((string) ($faq['question'] ?? '')))
                && ! empty(trim((string) ($faq['answer'] ?? ''))))
            ->map(fn (array $faq) => [
                'question' => trim((string) $faq['question']),
                'answer' => trim((string) $faq['answer']),
            ])
            ->values()
            ->all();
    }

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
        ->using(CategoryAttribute::class)
        ->withPivot([
            'is_enabled',
            'is_required',
            'is_filterable',
            'is_variant_axis',
            'sort_order',
        ])
        ->withTimestamps()
        ->orderByPivot('sort_order');
    }

    /**
     * Articles in this category
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_category')->withTimestamps();
    }
}
