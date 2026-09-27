<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTree([
            [
                'name' => 'قطعات خودرو',
                'slug' => 'car-parts',
                'children' => [
                    ['name' => 'موتور', 'slug' => 'engine', 'children' => [
                        ['name' => 'قطعات داخلی موتور', 'slug' => 'engine-internal-parts'],
                        ['name' => 'سرسیلندر و متعلقات', 'slug' => 'cylinder-head-parts'],
                        ['name' => 'سیستم سوخت‌رسانی', 'slug' => 'fuel-system'],
                        ['name' => 'سیستم خنک‌کاری', 'slug' => 'cooling-system'],
                        ['name' => 'تسمه و زنجیر', 'slug' => 'belts-and-chains'],
                    ]],
                    ['name' => 'سیستم انتقال قدرت', 'slug' => 'powertrain', 'children' => [
                        ['name' => 'کلاچ', 'slug' => 'clutch'],
                        ['name' => 'گیربکس', 'slug' => 'gearbox'],
                        ['name' => 'دیفرانسیل', 'slug' => 'differential'],
                        ['name' => 'پلوس', 'slug' => 'drive-shafts'],
                    ]],
                    ['name' => 'سیستم ترمز', 'slug' => 'brake-system', 'children' => [
                        ['name' => 'لنت ترمز', 'slug' => 'brake-pads'],
                        ['name' => 'دیسک ترمز', 'slug' => 'brake-discs'],
                        ['name' => 'کالیپر', 'slug' => 'brake-calipers'],
                        ['name' => 'قطعات هیدرولیک ترمز', 'slug' => 'brake-hydraulics'],
                    ]],
                    ['name' => 'سیستم تعلیق و فرمان', 'slug' => 'suspension-and-steering', 'children' => [
                        ['name' => 'کمک‌فنر', 'slug' => 'shock-absorbers'],
                        ['name' => 'فنر', 'slug' => 'springs'],
                        ['name' => 'سیبک', 'slug' => 'ball-joints'],
                        ['name' => 'طبق', 'slug' => 'control-arms'],
                        ['name' => 'جلوبندی', 'slug' => 'front-suspension'],
                    ]],
                    ['name' => 'برق و الکترونیک', 'slug' => 'electrical-and-electronics', 'children' => [
                        ['name' => 'باتری', 'slug' => 'batteries'],
                        ['name' => 'دینام', 'slug' => 'alternators'],
                        ['name' => 'استارت', 'slug' => 'starters'],
                        ['name' => 'سنسورها', 'slug' => 'sensors'],
                        ['name' => 'ECU', 'slug' => 'ecu'],
                    ]],
                    ['name' => 'سیستم تهویه و کولر', 'slug' => 'air-conditioning', 'children' => [
                        ['name' => 'کمپرسور کولر', 'slug' => 'ac-compressors'],
                        ['name' => 'کندانسور', 'slug' => 'condensers'],
                        ['name' => 'اواپراتور', 'slug' => 'evaporators'],
                        ['name' => 'فن', 'slug' => 'cooling-fans'],
                    ]],
                    ['name' => 'بدنه و روشنایی', 'slug' => 'body-and-lighting', 'children' => [
                        ['name' => 'چراغ', 'slug' => 'lights'],
                        ['name' => 'آینه', 'slug' => 'mirrors'],
                        ['name' => 'سپر', 'slug' => 'bumpers'],
                        ['name' => 'گلگیر', 'slug' => 'fenders'],
                        ['name' => 'قطعات بدنه', 'slug' => 'body-parts'],
                    ]],
                    ['name' => 'فیلترها و سرویس دوره‌ای', 'slug' => 'filters-and-service', 'children' => [
                        ['name' => 'فیلتر روغن', 'slug' => 'oil-filters'],
                        ['name' => 'فیلتر هوا', 'slug' => 'air-filters'],
                        ['name' => 'فیلتر بنزین', 'slug' => 'fuel-filters'],
                        ['name' => 'فیلتر کابین', 'slug' => 'cabin-filters'],
                    ]],
                    ['name' => 'مصرفی و متفرقه', 'slug' => 'consumables-and-accessories'],
                ],
            ],
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function seedTree(array $items, ?int $parentId = null): void
    {
        foreach ($items as $sortOrder => $item) {
            $category = Category::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'parent_id' => $parentId,
                    'name' => $item['name'],
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );

            $this->seedTree($item['children'] ?? [], $category->id);
        }
    }
}
