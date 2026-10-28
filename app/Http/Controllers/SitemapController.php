<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $baseUrl = 'https://royafashion.ir';

        $urls = [];

        $urls[] = rtrim($baseUrl, '/') . '/';
        $urls[] = rtrim($baseUrl, '/') . '/store';
        $urls[] = rtrim($baseUrl, '/') . '/articles';

        // Categories
        try {
            $categories = Category::query()
                ->where('is_active', true)
                ->select('slug')
                ->get();
            foreach ($categories as $category) {
                if (!empty($category->slug)) {
                    $urls[] = rtrim($baseUrl, '/') . '/c/' . rawurlencode($category->slug);
                }
            }
        } catch (\Throwable $e) {
        }

        // Products
        try {
            $products = Product::query()
                ->where('is_active', true)
                ->select('id', 'published_at')
                ->get();
            foreach ($products as $product) {
                if (!empty($product->id)) {
                    $urls[] = rtrim($baseUrl, '/') . '/products/' . $product->id;
                }
            }
        } catch (\Throwable $e) {
        }

        // Articles
        try {
            $articles = Article::query()
                ->where('status', 'published')
                ->select('slug', 'published_at')
                ->get();
            foreach ($articles as $article) {
                if (!empty($article->slug)) {
                    $urls[] = rtrim($baseUrl, '/') . '/articles/' . rawurlencode($article->slug);
                }
            }
        } catch (\Throwable $e) {
        }

        // Deduplicate and ensure all from base domain
        $urls = array_values(array_unique(array_filter($urls, function ($u) {
            if (!is_string($u)) {
                return false;
            }
            if (strpos($u, 'http://localhost') === 0 || strpos($u, 'http://127.0.0.1') === 0 || strpos($u, 'https://royagallery.ir') === 0) {
                return false;
            }
            return strpos($u, 'https://royafashion.ir') === 0;
        })));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
