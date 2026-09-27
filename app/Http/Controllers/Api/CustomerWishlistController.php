<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CustomerWishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->wishlistItems()
            ->with(['product.images', 'product.variants'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $items->map(function (WishlistItem $item) {
                $product = $item->product;

                return [
                    'id' => $item->id,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'slug' => $product->slug,
                        'price' => (float) $product->price,
                        'images' => $product->images->map(fn ($image) => [
                            'id' => $image->id,
                            'path' => $image->path,
                            'alt_text' => $image->alt_text,
                            'is_primary' => (bool) $image->is_primary,
                            'sort_order' => $image->sort_order,
                        ])->values(),
                        'stock' => $product->stock,
                        'in_stock' => $product->in_stock,
                    ],
                    'created_at' => $item->created_at?->toISOString(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
        ]);

        // firstOrCreate also recovers from a concurrent unique constraint violation.
        $item = $request->user()->wishlistItems()->firstOrCreate([
            'product_id' => $data['product_id'],
        ]);

        return response()->json([
            'data' => [
                'id' => $item->id,
                'product_id' => $item->product_id,
            ],
            'in_wishlist' => true,
        ], $item->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $product): Response
    {
        $request->user()->wishlistItems()->where('product_id', $product)->delete();

        return response()->noContent();
    }

    public function check(Request $request, string $product): JsonResponse
    {
        return response()->json([
            'in_wishlist' => $request->user()->wishlistItems()
                ->where('product_id', $product)->exists(),
        ]);
    }
}
