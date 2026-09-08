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

    if ($request->filled('category')) {
        $category = \App\Models\Category::where(
            'slug',
            $request->category
        )->firstOrFail();

        $categoryIds = collect([$category->id]);

        $collectChildren = function ($categories) use (&$collectChildren, &$categoryIds) {
            foreach ($categories as $child) {
                $categoryIds->push($child->id);

                $collectChildren($child->children);
            }
        };

        $collectChildren(
            $category->children()->get()
        );

        $query->whereHas('categories', function ($categoryQuery) use ($categoryIds) {
            $categoryQuery->whereIn(
                'categories.id',
                $categoryIds->unique()->values()
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Dynamic Attribute Filters
    |--------------------------------------------------------------------------
    */

    $filters = $request->except([
        'category',
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

        $query->whereHas('attributeValues', function ($attributeQuery) use (
            $attributeSlug,
            $values
        ) {
            $attributeQuery
                ->whereIn('value', $values)
                ->whereHas('attribute', function ($attributeQuery) use ($attributeSlug) {
                    $attributeQuery->where('slug', $attributeSlug);
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