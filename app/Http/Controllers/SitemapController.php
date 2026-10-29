<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Http\Response;

/**
 * Dynamic XML sitemap at /sitemap.xml listing all active categories,
 * active products, and published articles with <lastmod> timestamps,
 * using the semantic canonical URL structure (/category/{slug},
 * /product/{slug}).
 */
class SitemapController extends Controller
{
    public function __construct(
        private readonly SeoService $seo,
    ) {}

    public function index(): Response
    {
        $origin = $this->seo->origin();

        $entries = [
            ['url' => $origin . '/', 'lastmod' => null],
            ['url' => $origin . '/store', 'lastmod' => null],
            ['url' => $origin . '/articles', 'lastmod' => null],
        ];

        // Categories (active only)
        try {
            Category::query()
                ->where('is_active', true)
                ->select('slug', 'updated_at')
                ->orderBy('id')
                ->chunk(500, function ($categories) use ($origin, &$entries) {
                    foreach ($categories as $category) {
                        if (! empty($category->slug)) {
                            $entries[] = [
                                'url' => $origin . '/category/' . rawurlencode($category->slug),
                                'lastmod' => $category->updated_at,
                            ];
                        }
                    }
                });
        } catch (\Throwable) {
            // Sitemap stays valid even when a section fails to load.
        }

        // Products (active). Out-of-stock products still have valid canonical
        // pages and remain indexable; availability is expressed in Product JSON-LD.
        try {
            Product::query()
                ->where('is_active', true)
                ->select('slug', 'updated_at')
                ->orderBy('id')
                ->chunk(500, function ($products) use ($origin, &$entries) {
                    foreach ($products as $product) {
                        if (! empty($product->slug)) {
                            $entries[] = [
                                'url' => $origin . '/product/' . rawurlencode($product->slug),
                                'lastmod' => $product->updated_at,
                            ];
                        }
                    }
                });
        } catch (\Throwable) {
        }

        // Articles (published)
        try {
            Article::query()
                ->where('status', 'published')
                ->select('slug', 'published_at', 'updated_at')
                ->orderBy('id')
                ->chunk(500, function ($articles) use ($origin, &$entries) {
                    foreach ($articles as $article) {
                        if (! empty($article->slug)) {
                            $entries[] = [
                                'url' => $origin . '/articles/' . rawurlencode($article->slug),
                                'lastmod' => $article->updated_at ?: $article->published_at,
                            ];
                        }
                    }
                });
        } catch (\Throwable) {
        }

        // Deduplicate URLs, preserving first occurrence.
        $seen = [];
        $unique = [];
        foreach ($entries as $entry) {
            if (isset($seen[$entry['url']])) {
                continue;
            }
            $seen[$entry['url']] = true;
            $unique[] = $entry;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($unique as $entry) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['url'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
            if ($entry['lastmod'] !== null && $entry['lastmod'] !== '') {
                $xml .= '    <lastmod>' . $entry['lastmod']->toAtomString() . '</lastmod>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
