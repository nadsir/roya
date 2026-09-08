<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * Get active root categories with children.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with([
                'children' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $categories,
        ]);
    }
}