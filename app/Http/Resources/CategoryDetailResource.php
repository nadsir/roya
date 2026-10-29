<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'description' => $this->description,

            // SEO / GEO fields (for the client-side meta layer)
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'h1_title' => $this->h1_title,
            'top_description' => $this->top_description,
            'bottom_content' => $this->bottom_content,
            'faqs' => $this->normalized_faqs,

            'image' => $this->image,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ] : null),
            'ancestors' => $this->whenLoaded('ancestors',
                $this->ancestors->map(fn ($ancestor) => [
                    'id' => $ancestor->id,
                    'name' => $ancestor->name,
                    'slug' => $ancestor->slug,
                ])->values()
            ),
        ];
    }
}