<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductSchemaBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;

/**
 * Serves the Vue application shell for public product pages and embeds
 * schema.org Product JSON-LD in the initial HTML response, so crawlers can
 * detect rich-result markup without executing JavaScript.
 */
class ProductPageController extends Controller
{
    public function __construct(
        private readonly ProductSchemaBuilder $schemaBuilder,
    ) {}

    public function show(int $id): View|HttpResponse
    {
        $product = Product::query()
            ->select([
                'id', 'name', 'slug', 'sku', 'short_description',
                'description', 'price', 'stock',
            ])
            ->with([
                'images:id,product_id,variant_id,path,is_primary,sort_order',
                'variants:id,product_id,price,stock,is_active',
            ])
            ->where('is_active', true)
            ->find($id);

        if (! $product) {
            // No public product behind this URL: behave like the API and
            // return a real 404 instead of a soft 200 shell.
            return Response::view('welcome', ['productSchema' => null], 404);
        }

        return view('welcome', [
            'productSchema' => $this->schemaBuilder->build($product),
        ]);
    }
}
