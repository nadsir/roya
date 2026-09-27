<?php

namespace Database\Seeders;

use App\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = [
            ['name' => 'برند', 'slug' => 'brand', 'type' => 'select'],
            ['name' => 'کشور سازنده', 'slug' => 'country-of-origin', 'type' => 'select'],
            ['name' => 'وضعیت قطعه', 'slug' => 'part-condition', 'type' => 'select'],
            ['name' => 'محل نصب', 'slug' => 'installation-side', 'type' => 'select'],
            ['name' => 'محور', 'slug' => 'axle-position', 'type' => 'select'],
            ['name' => 'جنس', 'slug' => 'material', 'type' => 'select'],
            ['name' => 'نوع خودرو', 'slug' => 'vehicle-type', 'type' => 'select'],
            ['name' => 'نوع سوخت', 'slug' => 'fuel-type', 'type' => 'select'],
            ['name' => 'ولتاژ', 'slug' => 'voltage', 'type' => 'select'],
            ['name' => 'آمپراژ', 'slug' => 'amperage', 'type' => 'select'],
            ['name' => 'استاندارد', 'slug' => 'standard', 'type' => 'select'],
            ['name' => 'شماره فنی', 'slug' => 'part-number', 'type' => 'text'],
            ['name' => 'شماره OEM', 'slug' => 'oem-number', 'type' => 'text'],
            ['name' => 'کد موتور', 'slug' => 'engine-code', 'type' => 'text'],
            ['name' => 'وزن (کیلوگرم)', 'slug' => 'weight', 'type' => 'number'],
            ['name' => 'مدت گارانتی (ماه)', 'slug' => 'warranty-months', 'type' => 'number'],
        ];

        foreach ($attributes as $sortOrder => $attribute) {
            Attribute::updateOrCreate(
                ['slug' => $attribute['slug']],
                [
                    ...$attribute,
                    'is_filterable' => false,
                    'is_required' => false,
                    'sort_order' => $sortOrder,
                ]
            );
        }
    }
}
