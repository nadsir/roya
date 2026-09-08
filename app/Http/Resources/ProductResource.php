<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,

            'short_description' => $this->short_description,
            'description' => $this->description,

            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null
                ? (float) $this->compare_at_price
                : null,

            'is_featured' => (bool) $this->is_featured,

            'categories' => $this->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            })->values(),

            'images' => $this->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'path' => $image->path,
                    'alt_text' => $image->alt_text,
                    'is_primary' => (bool) $image->is_primary,
                    'sort_order' => $image->sort_order,
                ];
            })->values(),

            'attributes' => $this->attributeValues
                ->groupBy(function ($attributeValue) {
                    return $attributeValue->attribute->slug;
                })
                ->map(function ($values) {
                    return $values->map(function ($value) {
                        return [
                            'id' => $value->id,
                            'label' => $value->label,
                            'value' => $value->value,
                            'hex_color' => $value->hex_color,
                        ];
                    })->values();
                }),

            'variants' => $this->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price !== null
                        ? (float) $variant->price
                        : null,
                    'compare_at_price' => $variant->compare_at_price !== null
                        ? (float) $variant->compare_at_price
                        : null,
                    'stock' => $variant->stock,
                    'is_active' => (bool) $variant->is_active,

                    'attributes' => $variant->attributeValues
                        ->groupBy(function ($attributeValue) {
                            return $attributeValue->attribute->slug;
                        })
                        ->map(function ($values) {
                            return $values->map(function ($value) {
                                return [
                                    'id' => $value->id,
                                    'label' => $value->label,
                                    'value' => $value->value,
                                    'hex_color' => $value->hex_color,
                                ];
                            })->values();
                        }),
                ];
            })->values(),
        ];
    }
}