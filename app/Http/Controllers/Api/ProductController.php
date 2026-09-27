<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;

class ProductController extends Controller
{
public function index(Request $request)
{
    $query = Product::query()
        ->with([
            'categories:id,name,slug',
            'images:id,product_id,variant_id,path,alt_text,is_primary,sort_order',
            'attributeValues:id,attribute_id,label,value,hex_color,sort_order',
            'attributeValues.attribute:id,name,slug,type',
            'variants:id,product_id,sku,price,compare_at_price,stock,is_active,combination_key',
            'variants.attributeValues:id,attribute_id,label,value,hex_color',
            'variants.attributeValues.attribute:id,name,slug,type',
        ])
        ->where('is_active', true);

    /*
    |--------------------------------------------------------------------------
    | Category Filter
    |--------------------------------------------------------------------------
    */

    $categories = $request->input('category', $request->input('categories', []));
    $categories = is_array($categories) ? $categories : explode(',', (string) $categories);
    $categories = array_values(array_filter($categories));
    if ($categories) {
        $categoryIds = collect();
        foreach ($categories as $slug) {
            $category = \App\Models\Category::where('slug', $slug)->firstOrFail();
            $categoryIds->push($category->id);
            $collectChildren = function ($items) use (&$collectChildren, &$categoryIds) {
                foreach ($items as $child) { $categoryIds->push($child->id); $collectChildren($child->children); }
            };
            $collectChildren($category->children()->get());
        }
        $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds->unique()));
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Attribute Filters
    |--------------------------------------------------------------------------
    */

    $filters = $request->except([
        'category',
        'categories',
        'page',
    ]);

    foreach ($filters as $attributeSlug => $values) {
        $values = is_array($values)
            ? $values
            : explode(',', $values);

        $values = array_filter($values);

        if (empty($values)) {
            continue;
        }

        $query->where(function ($q) use ($attributeSlug, $values) {
            $q->whereHas('attributeValues', function ($attributeQuery) use ($attributeSlug, $values) {
                $attributeQuery->whereIn('value', $values)->whereHas('attribute', fn ($a) => $a->where('slug', $attributeSlug));
            })->orWhereHas('variants', function ($variantQuery) use ($attributeSlug, $values) {
                $variantQuery->where('is_active', true)->whereHas('attributeValues', function ($attributeQuery) use ($attributeSlug, $values) {
                    $attributeQuery->whereIn('value', $values)->whereHas('attribute', fn ($a) => $a->where('slug', $attributeSlug));
                });
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Sorting & Pagination
    |--------------------------------------------------------------------------
    */

    $products = $query
        ->orderByDesc('published_at')
        ->orderByDesc('created_at')
        ->paginate(12);

    return ProductResource::collection($products);
}
}