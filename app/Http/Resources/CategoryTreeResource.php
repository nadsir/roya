<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryTreeResource extends JsonResource
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
            'image' => $this->image,
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,

            // SEO / GEO fields (used by the admin category editor and the
            // public category API; harmless extra payload for tree consumers).
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'canonical_url' => $this->canonical_url,
            'h1_title' => $this->h1_title,
            'top_description' => $this->top_description,
            'bottom_content' => $this->bottom_content,
            'faqs' => $this->normalized_faqs,

            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
