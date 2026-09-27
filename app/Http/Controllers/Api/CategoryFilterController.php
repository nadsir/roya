<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryFilterResource;
use App\Models\Category;

class CategoryFilterController extends Controller
{
    public function index(string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        // والدها + دسته فعلی
        $categories = $category->ancestors()
            ->push($category);

        // دریافت فیلترهای تمام سطوح
        $filters = $categories
            ->flatMap(function (Category $category) {
                return $category->attributes()
                    ->wherePivot('is_filterable', true)
                    ->with([
                        'values' => function ($query) {
                            $query->orderBy('sort_order');
                        },
                    ])
                    ->get();
            })
            ->unique('id')
            ->sortBy(function ($attribute) {
                return $attribute->pivot->sort_order;
            })
            ->values();

        return CategoryFilterResource::collection($filters);
    }
}
