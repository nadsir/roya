<?php

namespace App\Services;

use App\Models\Product;

/**
 * Builds schema.org Product structured data (JSON-LD) for a public product.
 *
 * Emits real, verifiable fields including customer ratings/reviews when
 * available. Returns null when the product lacks the data Google needs for
 * a valid Product entity.
 */
class ProductSchemaBuilder
{
    /**
     * Public origin used for absolute URLs. The storefront's client-side SEO
     * uses the same hardcoded production origin (see ProductDetail.vue).
     */
    private const PRODUCTION_ORIGIN = 'https://royafashion.ir';

    public function build(Product $product): ?array
    {
        $price = $this->lowestActivePrice($product);

        // Without a real price there is nothing valid to offer; skip markup
        // rather than emitting a fabricated or zero price.
        if ($price === null || $price <= 0) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
        ];

        $description = $this->description($product);
        if ($description !== null) {
            $schema['description'] = $description;
        }

        $images = $this->images($product);
        if ($images !== []) {
            $schema['image'] = $images;
        }

        if (is_string($product->sku) && trim($product->sku) !== '') {
            $schema['sku'] = trim($product->sku);
        }

        $schema['offers'] = [
            '@type' => 'Offer',
            'url' => $this->productUrl($product),
            'priceCurrency' => 'IRR',
            'price' => $this->formatPrice($price),
            'availability' => $product->in_stock
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
        ];

        $this->addReviews($schema, $product);

        return $schema;
    }

    /**
     * The canonical public URL of the product page (slug-based).
     */
    public function productUrl(Product $product): string
    {
        return $this->origin() . '/product/' . rawurlencode($product->slug);
    }

    private function origin(): string
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        // In local/test environments APP_URL points at localhost; keep the
        // schema consistent with the live canonical URLs the Vue layer emits.
        $host = parse_url($appUrl, PHP_URL_HOST);
        if ($host === 'localhost' || $host === '127.0.0.1' || $host === null) {
            return self::PRODUCTION_ORIGIN;
        }

        return $appUrl;
    }

    /**
     * The cheapest real purchasable price: the minimum active variant price
     * when variants exist, otherwise the product's own price.
     */
    private function lowestActivePrice(Product $product): ?float
    {
        if ($product->variants->isNotEmpty()) {
            $prices = $product->variants
                ->where('is_active', true)
                ->map(fn ($variant) => (float) $variant->effective_price)
                ->filter(fn (float $price) => $price > 0);

            return $prices->isEmpty() ? null : (float) $prices->min();
        }

        return $product->price !== null ? (float) $product->price : null;
    }

    private function description(Product $product): ?string
    {
        $raw = $product->short_description ?: $product->description;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');

        return $text === '' ? null : mb_substr($text, 0, 500);
    }

    /**
     * Absolute URLs of the product's images (up to five), primary first.
     * Only product-level images (not variant-specific) are used.
     *
     * @return array<int, string>
     */
    private function images(Product $product): array
    {
        return $product->images
            ->whereNull('variant_id')
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->pluck('path')
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path) => $this->absoluteImageUrl(trim($path)))
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    private function absoluteImageUrl(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $this->origin() . $path;
        }

        // Stored relative paths live on the public disk, exposed via
        // the `storage` symlink — same convention as the Vue frontend.
        return $this->origin() . '/storage/' . $path;
    }

    /**
     * Attach aggregate rating and individual reviews derived from approved
     * customer comments that carry a rating.
     *
     * @param array<string, mixed> $schema
     */
    private function addReviews(array &$schema, Product $product): void
    {
        $reviews = $product->comments()
            ->where('status', 'approved')
            ->whereNotNull('rating')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        if ($reviews->isEmpty()) {
            return;
        }

        $average = round((float) $reviews->avg('rating'), 1);

        $schema['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($average, 1, '.', ''),
            'reviewCount' => $reviews->count(),
            'bestRating' => '5',
            'worstRating' => '1',
        ];

        $schema['review'] = $reviews->map(function ($comment): array {
            $node = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) $comment->rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $comment->user?->name ?: 'Anonymous',
                ],
            ];

            if (is_string($comment->body) && trim($comment->body) !== '') {
                $node['reviewBody'] = trim($comment->body);
            }

            if ($comment->created_at !== null) {
                $node['datePublished'] = $comment->created_at->toDateString();
            }

            if (is_string($comment->title) && trim($comment->title) !== '') {
                $node['name'] = trim($comment->title);
            }

            return $node;
        })->values()->all();
    }

    private function formatPrice(float $price): string
    {
        return $price === floor($price)
            ? (string) (int) $price
            : rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }
}
