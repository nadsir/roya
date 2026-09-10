<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $parentCategories = Category::query()
            ->whereNull('parent_id')
            ->with('children')
            ->get();

        foreach ($parentCategories as $parent) {
            $attributeIds = $parent->attributes()
                ->pluck('attributes.id')
                ->all();

            if (empty($attributeIds)) {
                continue;
            }

            foreach ($parent->children as $child) {
                $child->attributes()->syncWithoutDetaching($attributeIds);
            }
        }
    }
}