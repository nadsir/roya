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
            'published_at' => optional($this->published_at)->toISOString(),

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

            'custom_attributes' => $this->customAttributeValues
                ->map(function ($customValue) {
                    return [
                        'id' => $customValue->id,
                        'name' => $customValue->attribute->name,
                        'slug' => $customValue->attribute->slug,
                        'type' => $customValue->value_type,
                        'value' => $customValue->typedValue(),
                    ];
                })
                ->values(),

            'vehicle_compatibility' => $this->vehicleEngines
                ->map(function ($engine) {
                    $trim = $engine->trim;
                    $generation = $trim->generation;
                    $model = $generation->model;
                    $brand = $model->brand;

                    return [
                        'engine' => [
                            'id' => $engine->id,
                            'name' => $engine->name,
                            'slug' => $engine->slug,
                        ],
                        'trim' => [
                            'id' => $trim->id,
                            'name' => $trim->name,
                        ],
                        'generation' => [
                            'id' => $generation->id,
                            'name' => $generation->name,
                            'year_start' => $generation->year_start,
                            'year_end' => $generation->year_end,
                        ],
                        'model' => [
                            'id' => $model->id,
                            'name' => $model->name,
                        ],
                        'brand' => [
                            'id' => $brand->id,
                            'name' => $brand->name,
                        ],
                    ];
                })
                ->values(),

                'in_stock' => $this->in_stock,

            'has_variants' => $this->variants->isNotEmpty(),

            'variants' => $this->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variant->effective_price,
                    'compare_at_price' => $variant->effective_compare_at_price,
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
