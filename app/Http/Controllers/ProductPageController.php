<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;

/**
 * Serves the Vue application shell for public product pages with
 * server-rendered SEO: dynamic meta tags, canonical, OpenGraph, and
 * JSON-LD structured data (Organization, Product with offers and return
 * policy, BreadcrumbList) embedded in the initial HTML response.
 *
 * A semantic, crawlable SEO block (H1, description, fabric/care/size
 * tables) is rendered inside the shell; the Vue application replaces it
 * once hydrated.
 */
class ProductPageController extends Controller
{
    public function __construct(
        private readonly SeoService $seo,
    ) {}

    public function show(Product $product): View|HttpResponse
    {
        $product = Product::query()
            ->select([
                'id', 'name', 'slug', 'sku', 'short_description', 'description',
                'meta_title', 'meta_description', 'canonical_url',
                'brand', 'material', 'care_instructions', 'size_chart',
                'price', 'stock', 'compare_at_price', 'published_at',
            ])
            ->with([
                'images:id,product_id,variant_id,path,alt_text,is_primary,sort_order',
                'variants:id,product_id,price,stock,is_active',
                'categories:id,name,slug,parent_id',
                'comments.user:id,name',
            ])
            ->where('is_active', true)
            ->find($product->id);

        if (! $product) {
            // No public product behind this URL: behave like the API and
            // return a real 404 instead of a soft 200 shell.
            return Response::view('welcome', $this->emptySeo(), 404);
        }

        $canonical = $this->seo->productCanonical($product);
        $title = $product->seo_title . ' | ' . 'گالری رویا';
        $description = $product->seo_description;
        $image = $this->productImage($product);

        return view('welcome', [
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'ogType' => 'product',
                'social' => $this->seo->socialMeta([
                    'title' => $title,
                    'description' => $description,
                    'url' => $canonical,
                    'type' => 'product',
                    'image' => $image,
                ]),
                'jsonLd' => array_values(array_filter([
                    $this->seo->organizationSchema(),
                    $this->seo->productSchema($product),
                    $this->seo->productBreadcrumbs($product, $product->categories),
                ])),
            ],
            'seoBlock' => [
                'product' => $product,
                'h1' => $product->name,
                'material' => $product->material,
                'careInstructions' => $product->care_instructions,
                'sizeChart' => $this->normalizeSizeChart($product->size_chart),
                'inStock' => $product->in_stock,
            ],
        ]);
    }

    /**
     * Normalize stored size_chart JSON to a machine-readable table shape:
     * ['columns' => string[], 'rows' => array<int, array<int, string>>]
     * or null when unusable.
     *
     * @param mixed $sizeChart
     * @return array{columns: array<int, string>, rows: array<int, array<int, string>>}|null
     */
    private function normalizeSizeChart(mixed $sizeChart): ?array
    {
        if (! is_array($sizeChart) || $sizeChart === []) {
            return null;
        }

        // Shape A: {'columns': [...], 'rows': [[...], ...]}
        if (isset($sizeChart['columns']) && is_array($sizeChart['columns']) && ! empty($sizeChart['columns'])) {
            $columns = array_values(array_map('strval', $sizeChart['columns']));
            $rows = collect($sizeChart['rows'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(fn (array $row) => array_values(array_map(fn ($cell) => (string) $cell, $row)))
                ->values()
                ->all();

            return $rows === [] ? null : ['columns' => $columns, 'rows' => $rows];
        }

        // Shape B: [{'size': 'M', 'bust': '92', ...}, ...]
        $columns = collect($sizeChart)
            ->filter(fn ($row) => is_array($row))
            ->flatMap(fn (array $row) => array_keys($row))
            ->unique()
            ->values()
            ->map(fn ($key) => (string) $key)
            ->all();

        if ($columns === []) {
            return null;
        }

        $rows = collect($sizeChart)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => array_map(fn ($column) => (string) ($row[$column] ?? ''), $columns))
            ->values()
            ->all();

        return $rows === [] ? null : ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Absolute URL of the product's primary image, when present.
     */
    private function productImage(Product $product): ?string
    {
        $image = $product->images
            ->whereNull('variant_id')
            ->sortByDesc('is_primary')
            ->first();

        $path = trim((string) ($image->path ?? ''));

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return $this->seo->origin() . $path;
        }

        // Product uploads live on the public disk behind the /storage symlink.
        return $this->seo->origin() . '/storage/' . $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySeo(): array
    {
        return [
            'seo' => null,
            'seoBlock' => null,
        ];
    }
}
