<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Support\Collection;
use LogicException;

class EffectiveCategoryAttributesResolver
{
    /**
     * Resolve direct and inherited attributes from root to the selected category.
     * A direct configuration on a child replaces the same attribute from an ancestor.
     *
     * @return Collection<int, Attribute>
     */
    public function for(Category $category): Collection
    {
        $categoryIds = $this->ancestorPathIds($category);

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->with([
                'attributes.values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->get()
            ->keyBy('id');

        $effectiveAttributes = collect();

        foreach ($categoryIds as $categoryId) {
            $pathCategory = $categories->get($categoryId);

            if ($pathCategory === null) {
                continue;
            }

            foreach ($pathCategory->attributes as $attribute) {
                if ($attribute->pivot->is_enabled) {
                    $effectiveAttributes->put($attribute->id, $attribute);
                } else {
                    $effectiveAttributes->forget($attribute->id);
                }
            }
        }

        return $effectiveAttributes
            ->sortBy(fn (Attribute $attribute) => $attribute->pivot->sort_order)
            ->values();
    }

    /**
     * Resolve effective attributes for an already-loaded category collection.
     * This avoids a query per category in metadata endpoints.
     *
     * @param Collection<int, Category> $categories
     * @return Collection<int, Collection<int, Attribute>>
     */
    public function forMany(Collection $categories): Collection
    {
        $categoriesById = $categories->keyBy('id');
        $resolved = collect();

        foreach ($categories as $category) {
            $path = [];
            $seen = [];
            $currentId = $category->id;

            while ($currentId !== null) {
                if (isset($seen[$currentId])) {
                    throw new LogicException('Category hierarchy contains a cycle.');
                }

                $seen[$currentId] = true;
                $path[] = $currentId;
                $currentId = $categoriesById->get($currentId)?->parent_id;
            }

            $attributes = collect();

            foreach (array_reverse($path) as $categoryId) {
                foreach ($categoriesById->get($categoryId)?->attributes ?? [] as $attribute) {
                    if ($attribute->pivot->is_enabled) {
                        $attributes->put($attribute->id, $attribute);
                    } else {
                        $attributes->forget($attribute->id);
                    }
                }
            }

            $resolved->put(
                $category->id,
                $attributes
                    ->sortBy(fn (Attribute $attribute) => $attribute->pivot->sort_order)
                    ->values()
            );
        }

        return $resolved;
    }

    /**
     * @return list<int>
     */
    private function ancestorPathIds(Category $category): array
    {
        $parentIds = Category::query()->pluck('parent_id', 'id');
        $path = [];
        $seen = [];
        $currentId = $category->id;

        while ($currentId !== null) {
            if (isset($seen[$currentId])) {
                throw new LogicException('Category hierarchy contains a cycle.');
            }

            $seen[$currentId] = true;
            $path[] = $currentId;
            $currentId = $parentIds->get($currentId);
        }

        return array_reverse($path);
    }
}
