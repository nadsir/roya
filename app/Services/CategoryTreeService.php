<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryTreeService
{
    /**
     * Build a category tree from one ordered query, with no depth limit.
     *
     * @return Collection<int, Category>
     */
    public function tree(bool $activeOnly = true): Collection
    {
        $query = Category::query()
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $this->build($query->get());
    }

    /**
     * @param Collection<int, Category> $categories
     * @return Collection<int, Category>
     */
    public function build(Collection $categories): Collection
    {
        $childrenByParent = [];

        foreach ($categories as $category) {
            $parentKey = $category->parent_id ?? 0;
            $childrenByParent[$parentKey][] = $category;
        }

        foreach ($categories as $category) {
            $category->setRelation(
                'children',
                collect($childrenByParent[$category->id] ?? [])
            );
        }

        return collect($childrenByParent[0] ?? []);
    }

    /**
     * @param Collection<int, Category> $categories
     * @return Collection<int, int>
     */
    public function descendantIdsFor(
        Collection $categories,
        bool $activeOnly = true
    ): Collection {
        if ($categories->isEmpty()) {
            return collect();
        }

        $query = Category::query()->select(['id', 'parent_id']);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $childrenByParent = $query->get()->groupBy('parent_id');
        $pendingIds = $categories->pluck('id')->all();
        $descendantIds = [];
        $seen = [];

        while ($pendingIds !== []) {
            $categoryId = array_shift($pendingIds);

            if (isset($seen[$categoryId])) {
                continue;
            }

            $seen[$categoryId] = true;
            $descendantIds[] = $categoryId;

            foreach ($childrenByParent->get($categoryId, collect()) as $child) {
                $pendingIds[] = $child->id;
            }
        }

        return collect($descendantIds);
    }

    public function wouldCreateCycle(Category $category, int $parentId): bool
    {
        if ($category->id === $parentId) {
            return true;
        }

        $parentIds = Category::query()->pluck('parent_id', 'id');
        $seen = [];
        $currentId = $parentId;

        while ($currentId !== null) {
            if ($currentId === $category->id || isset($seen[$currentId])) {
                return true;
            }

            $seen[$currentId] = true;
            $currentId = $parentIds->get($currentId);
        }

        return false;
    }
}
