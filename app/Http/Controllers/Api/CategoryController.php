<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryDetailResource;
use App\Http\Resources\CategoryTreeResource;
use App\Models\Category;
use App\Services\CategoryTreeService;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    /**
     * Get the active category tree with no depth limit.
     */
    public function index()
    {
        return CategoryTreeResource::collection(
            $this->categoryTree->tree()
        );
    }

    /**
     * Get a single active category with its parent and ancestors (breadcrumb path).
     */
    public function show(string $slug)
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('parent')
            ->firstOrFail();

        $category->setRelation('ancestors', $category->ancestors());

        return new CategoryDetailResource($category);
    }
}
