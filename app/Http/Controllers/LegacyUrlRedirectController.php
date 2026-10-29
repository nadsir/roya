<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;

/**
 * Permanent (301) redirects from legacy URL structures to the semantic,
 * slug-based canonical URLs:
 *
 * - /c/{slug}       -> /category/{slug}
 * - /products/{id}  -> /product/{slug}
 */
class LegacyUrlRedirectController extends Controller
{
    public function redirectToCategory(string $slug): RedirectResponse
    {
        // Slug is already URL-safe; keep the decoded form for the path.
        $target = '/category/' . rawurlencode(urldecode($slug));

        return redirect()->to($target, 301);
    }

    public function redirectToProduct(int $id): RedirectResponse
    {
        $product = Product::query()
            ->where('is_active', true)
            ->find($id);

        if (! $product || trim((string) $product->slug) === '') {
            // No canonical target: the legacy URL no longer exists.
            abort(404);
        }

        return redirect()->to('/product/' . rawurlencode($product->slug), 301);
    }
}
