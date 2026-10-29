<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;

/**
 * Serves the Vue application shell for public category pages with
 * server-rendered SEO: dynamic meta tags, canonical, OpenGraph, and
 * JSON-LD structured data (Organization, BreadcrumbList, FAQPage) are
 * embedded in the initial HTML response so crawlers and AI engines can
 * index the page without executing JavaScript.
 *
 * A semantic, crawlable SEO block (H1, top description, initial product
 * grid, bottom styling content, FAQ) is rendered inside the shell; the
 * Vue application replaces it once hydrated.
 */
class CategoryPageController extends Controller
{
    /** Number of products in the server-rendered grid. */
    private const SSR_PRODUCT_LIMIT = 12;

    public function __construct(
        private readonly SeoService $seo,
    ) {}

    public function show(Request $request, Category $category): View|HttpResponse
    {
        if (! $category->is_active) {
            return Response::view('welcome', $this->emptySeo(), 404);
        }

        $page = $this->paginationPage($request);
        $ancestors = $category->ancestors();

        // Products shared with the server-rendered block and Vue hydration.
        $products = Product::query()
            ->select([
                'id', 'name', 'slug', 'sku', 'short_description',
                'price', 'compare_at_price', 'is_active', 'published_at',
                'sort_order', 'stock',
            ])
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $category->id))
            ->where('is_active', true)
            ->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->with([
                'images:id,product_id,variant_id,path,alt_text,is_primary,sort_order',
            ])
            ->forPage($page, self::SSR_PRODUCT_LIMIT)
            ->get();

        $canonical = $this->seo->categoryCanonical($category, $page);
        $title = $category->seo_title . ' | ' . 'گالری رویا';
        $description = $category->seo_description;
        $image = $this->categoryImage($category);

        return view('welcome', [
            'seo' => [
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index,follow',
                'ogType' => 'website',
                'social' => $this->seo->socialMeta([
                    'title' => $title,
                    'description' => $description,
                    'url' => $canonical,
                    'image' => $image,
                ]),
                'jsonLd' => array_values(array_filter([
                    $this->seo->organizationSchema(),
                    $this->seo->categoryBreadcrumbs($category, $ancestors),
                    $category->normalized_faqs !== [] ? $this->seo->faqSchema($category->normalized_faqs) : null,
                ])),
            ],
            'seoBlock' => [
                'category' => $category,
                'h1' => $category->h1_title,
                'topDescription' => $category->top_description,
                'bottomContent' => $category->bottom_content,
                'faqs' => $category->normalized_faqs,
                'products' => $products,
                'productCount' => $category->products()->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Safe pagination page number from the request (1 when absent/invalid).
     */
    private function paginationPage(Request $request): int
    {
        $page = (int) $request->query('page', 1);

        return $page > 0 ? $page : 1;
    }

    private function categoryImage(Category $category): ?string
    {
        $path = trim((string) ($category->image ?? ''));

        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = $path[0] === '/' ? $path : '/' . $path;

        return $this->seo->origin() . $path;
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
