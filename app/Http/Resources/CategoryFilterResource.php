<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryFilterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $values = $this->facetValues ?? $this->values;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,

            'is_filterable' => (bool) $this->pivot?->is_filterable,

            'is_required' => (bool) $this->pivot?->is_required,

            'is_variant_axis' => (bool) $this->pivot?->is_variant_axis,

            'sort_order' => (int) $this->pivot?->sort_order,

            'values' => $values->map(function ($value) {
                if (is_array($value)) {
                    return [
                        'id' => $value['id'] ?? null,
                        'label' => $value['label'] ?? null,
                        'value' => $value['value'] ?? null,
                        'hex_color' => $value['hex_color'] ?? null,
                        'count' => $value['count'] ?? 0,
                    ];
                }

                return [
                    'id' => $value->id,
                    'label' => $value->label,
                    'value' => $value->value,
                    'hex_color' => $value->hex_color,
                    'count' => 0,
                ];
            })->values(),
        ];
    }
}
