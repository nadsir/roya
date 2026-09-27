<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryFilterResource;
use App\Models\Category;
use App\Services\EffectiveCategoryAttributesResolver;
use App\Services\ProductFacetService;
use Illuminate\Http\Request;

class EffectiveCategoryFilterController extends Controller
{
    public function __construct(
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes,
        private readonly ProductFacetService $facets
    ) {
    }

    public function index(Request $request, string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $attributes = $this->effectiveAttributes
            ->for($category)
            ->filter(fn ($attribute) => $attribute->pivot->is_filterable)
            ->values();

        return CategoryFilterResource::collection(
            $this->facets->apply($request, $category, $attributes)
        );
    }
}
