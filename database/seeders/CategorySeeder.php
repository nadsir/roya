<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'لباس',
                'slug' => 'clothing',
                'children' => [
                    ['name' => 'مانتو', 'slug' => 'mantos'],
                    ['name' => 'شومیز', 'slug' => 'shirts'],
                    ['name' => 'لباس مجلسی', 'slug' => 'evening-dresses'],
                    ['name' => 'لباس روزمره', 'slug' => 'casual-dresses'],
                ],
            ],

            [
                'name' => 'کیف',
                'slug' => 'bags',
                'children' => [
                    ['name' => 'کیف دستی', 'slug' => 'handbags'],
                    ['name' => 'کیف دوشی', 'slug' => 'shoulder-bags'],
                    ['name' => 'کیف مجلسی', 'slug' => 'evening-bags'],
                ],
            ],

            [
                'name' => 'کفش',
                'slug' => 'shoes',
                'children' => [
                    ['name' => 'کفش زنانه', 'slug' => 'women-shoes'],
                    ['name' => 'صندل', 'slug' => 'sandals'],
                    ['name' => 'بوت', 'slug' => 'boots'],
                ],
            ],

            [
                'name' => 'عینک',
                'slug' => 'glasses',
                'children' => [
                    ['name' => 'عینک طبی', 'slug' => 'optical-glasses'],
                    ['name' => 'عینک آفتابی', 'slug' => 'sunglasses'],
                ],
            ],

            [
                'name' => 'اکسسوری',
                'slug' => 'accessories',
                'children' => [
                    ['name' => 'زیورآلات', 'slug' => 'jewelry'],
                    ['name' => 'ساعت', 'slug' => 'watches'],
                    ['name' => 'سایر', 'slug' => 'other-accessories'],
                ],
            ],
        ];

        foreach ($categories as $index => $categoryData) {
            $children = $categoryData['children'];

            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );

            foreach ($children as $childIndex => $childData) {
                Category::updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'parent_id' => $category->id,
                        'name' => $childData['name'],
                        'is_active' => true,
                        'sort_order' => $childIndex,
                    ]
                );
            }
        }
    }
}