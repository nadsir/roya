<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RoyaProductSocialPublisher
{
    // Short-lived coordination for the separate create/upload requests, not a queue.
    public function prepare(Product $product, int $imageCount): ?string
    {
        if ($imageCount === 0) {
            Log::info('Product webhook skipped: no images selected.', ['product_id' => $product->id]);
            return null;
        }

        try {
            $token = Str::random(64);
            Cache::put($this->key($product), [
                'token' => $token,
                'image_count' => $imageCount,
            ], now()->addDay());
            return $token;
        } catch (Throwable $error) {
            Log::warning('Product webhook preparation failed.', ['product_id' => $product->id, 'error' => $error->getMessage()]);
            return null;
        }
    }

    public function complete(Product $product, string $token): void
    {
        try {
            Cache::lock($this->key($product).':lock', 30)->get(function () use ($product, $token) {
                $pending = Cache::get($this->key($product));
                if (!$pending || !hash_equals($pending['token'], $token)) {
                    Log::info('Product webhook skipped: invalid or consumed creation token.', ['product_id' => $product->id]);
                    return;
                }
                if ($product->images()->count() !== $pending['image_count']) {
                    Log::warning('Product webhook skipped: image uploads incomplete.', ['product_id' => $product->id]);
                    return;
                }

                // Consume before HTTP: retries or concurrent completions must not publish twice.
                Cache::forget($this->key($product));
                $this->publish($product);
            });
        } catch (Throwable $error) {
            Log::warning('Product webhook completion failed.', ['product_id' => $product->id, 'error' => $error->getMessage()]);
        }
    }

    public function publish(Product $product): void
    {
        try {
            $product = $product->fresh(['categories', 'images']);
            $images = $product->images()->reorder()->orderBy('sort_order')->orderBy('id')->get();
            if ($images->isEmpty()) {
                Log::info('Product webhook skipped: no stored images.', ['product_id' => $product->id]);
                return;
            }
            $webhook = config('services.n8n.product_published_webhook_url');
            if (!$webhook) {
                Log::warning('Product webhook skipped: URL not configured.', ['product_id' => $product->id]);
                return;
            }

            $baseUrl = rtrim(config('app.url'), '/');
            $imagePayload = $images->map(function ($image) use ($baseUrl) {
                $url = Storage::disk('public')->url($image->path);
                if (!Str::startsWith($url, ['http://', 'https://'])) {
                    $url = $baseUrl.'/'.ltrim($url, '/');
                }
                return [
                    'id' => $image->id,
                    'url' => $url,
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => (int) $image->sort_order,
                    'alt_text' => $image->alt_text,
                ];
            });
            $primary = $imagePayload->firstWhere('is_primary', true) ?? $imagePayload->first();
            $payload = [
                'event' => 'product.published',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'currency' => 'IRT',
                    'category' => $product->categories->sortBy('id')->first()?->name,
                    'description' => $product->description,
                    'product_url' => $baseUrl.'/products/'.rawurlencode($product->slug),
                    'image_url' => $primary['url'],
                    'images' => $imagePayload->all(),
                ],
            ];

            $response = Http::connectTimeout(3)->timeout(10)->post($webhook, $payload);
            if (!$response->successful()) {
                Log::warning('Product webhook HTTP error.', ['product_id' => $product->id, 'status' => $response->status()]);
            }
        } catch (Throwable $error) {
            Log::warning('Product webhook failed.', ['product_id' => $product->id, 'error' => $error->getMessage()]);
        }
    }

    private function key(Product $product): string
    {
        return 'roya:product-published:'.$product->id;
    }
}
