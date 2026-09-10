<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminProductImageController extends Controller
{
    /**
     * Upload product image
     */
    public function store(
        Request $request,
        Product $product
    ) {
        $data = $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $isPrimary = ! $product->images()->exists();

        $path = $request
            ->file('image')
            ->store(
                'products',
                'public'
            );

        $image = $product->images()->create([
            'path' => $path,

            'alt_text' =>
                $data['alt_text']
                ?? $product->name,

            'is_primary' => $isPrimary,

            'sort_order' =>
                ($product->images()->max('sort_order') ?? 0)
                + 1,
        ]);

        return response()->json(
            $image,
            201
        );
    }

    /**
     * Set image as primary
     */
    public function setPrimary(
        Product $product,
        ProductImage $image
    ) {
        $this->ensureImageBelongsToProduct(
            $product,
            $image
        );

        DB::transaction(function () use (
            $product,
            $image
        ) {
            $product->images()->update([
                'is_primary' => false,
            ]);

            $image->update([
                'is_primary' => true,
            ]);
        });

        return response()->json(
            $product->images()
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Reorder product images
     */
    public function reorder(
        Request $request,
        Product $product
    ) {
        $data = $request->validate([
            'image_ids' => [
                'required',
                'array',
            ],

            'image_ids.*' => [
                'integer',
            ],
        ]);

        $imageIds = $data['image_ids'];

        $productImageIds = $product
            ->images()
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $submittedImageIds = collect($imageIds)
            ->sort()
            ->values()
            ->all();

        if ($productImageIds !== $submittedImageIds) {
            return response()->json([
                'message' => 'ترتیب تصاویر نامعتبر است.',
            ], 422);
        }

        DB::transaction(function () use (
            $product,
            $imageIds
        ) {
            foreach ($imageIds as $index => $imageId) {
                $product
                    ->images()
                    ->whereKey($imageId)
                    ->update([
                        'sort_order' => $index + 1,
                    ]);
            }
        });

        return response()->json(
            $product->images()
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Delete product image safely
     */
    public function destroy(
        Product $product,
        ProductImage $image
    ) {
        $this->ensureImageBelongsToProduct(
            $product,
            $image
        );

        DB::transaction(function () use (
            $product,
            $image
        ) {
            $wasPrimary = $image->is_primary;

            Storage::disk('public')
                ->delete($image->path);

            $image->delete();

            /*
             * If the deleted image was primary,
             * automatically promote the first remaining image.
             */
            if ($wasPrimary) {
                $newPrimary = $product
                    ->images()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first();

                if ($newPrimary) {
                    $newPrimary->update([
                        'is_primary' => true,
                    ]);
                }
            }

            /*
             * Normalize sort order.
             */
            $remainingImages = $product
                ->images()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach (
                $remainingImages as $index => $remainingImage
            ) {
                $remainingImage->update([
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return response()->json(
            $product->images()
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Ensure image belongs to selected product.
     */
    protected function ensureImageBelongsToProduct(
        Product $product,
        ProductImage $image
    ): void {
        abort_unless(
            $image->product_id === $product->id,
            404
        );
    }
}