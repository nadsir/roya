<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            [
                'name' => 'رنگ',
                'slug' => 'color',
                'type' => 'color',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 1,
            ],

            [
                'name' => 'سایز لباس',
                'slug' => 'size',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 2,
            ],

            [
                'name' => 'جنس',
                'slug' => 'material',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 3,
            ],

            [
                'name' => 'نوع کیف',
                'slug' => 'bag-type',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 4,
            ],

            [
                'name' => 'نوع کفش',
                'slug' => 'shoe-type',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 5,
            ],

            [
                'name' => 'سایز کفش',
                'slug' => 'shoe-size',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 6,
            ],

            [
                'name' => 'جنس فریم',
                'slug' => 'frame-material',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 7,
            ],

            [
                'name' => 'شکل فریم',
                'slug' => 'frame-shape',
                'type' => 'select',
                'is_filterable' => true,
                'is_required' => false,
                'sort_order' => 8,
            ],
        ];

        foreach ($attributes as $attributeData) {
            Attribute::updateOrCreate(
                ['slug' => $attributeData['slug']],
                $attributeData
            );
        }

        /*
         * لباس
         */
        $this->syncCategoryAttributes(
            'clothing',
            ['material', 'size', 'color']
        );

        /*
         * کیف
         */
        $this->syncCategoryAttributes(
            'bags',
            ['bag-type', 'material', 'color']
        );

        /*
         * کفش
         */
        $this->syncCategoryAttributes(
            'shoes',
            ['shoe-type', 'shoe-size', 'material', 'color']
        );

        /*
         * عینک
         */
        $this->syncCategoryAttributes(
            'glasses',
            ['frame-material', 'frame-shape', 'color']
        );

        /*
         * اکسسوری
         */
        $this->syncCategoryAttributes(
            'accessories',
            ['material', 'color']
        );
    }

    private function syncCategoryAttributes(
        string $categorySlug,
        array $attributeSlugs
    ): void {
        $category = Category::where('slug', $categorySlug)->first();

        if (!$category) {
            return;
        }

        foreach ($attributeSlugs as $index => $attributeSlug) {
            $attribute = Attribute::where('slug', $attributeSlug)->first();

            if (!$attribute) {
                continue;
            }

            $category->attributes()->syncWithoutDetaching([
                $attribute->id => [
                    'is_required' => false,
                    'sort_order' => $index,
                ],
            ]);
        }
    }
}