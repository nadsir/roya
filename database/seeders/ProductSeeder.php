<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['front-brake-pad-peugeot-405', 'لنت ترمز جلو پژو 405', 'CAR-BRK-405-FP', 'brake-pads', 890000, 18, ['brand' => 'bosch', 'axle-position' => 'front', 'material' => 'semi-metallic', 'standard' => 'ece-r90'], ['part-number' => 'BP-405-F', 'oem-number' => '4252-65', 'weight' => 1.25, 'warranty-months' => 12]],
            ['rear-brake-pad-peugeot-405', 'لنت ترمز عقب پژو 405', 'CAR-BRK-405-RP', 'brake-pads', 760000, 12, ['brand' => 'valeo', 'axle-position' => 'rear', 'material' => 'ceramic', 'standard' => 'ece-r90'], ['part-number' => 'BP-405-R', 'oem-number' => '4253-14', 'weight' => 1.1, 'warranty-months' => 12]],
            ['brake-disc-peugeot-206', 'دیسک ترمز جلو پژو 206', 'CAR-BRK-206-D', 'brake-discs', 1450000, 9, ['brand' => 'sachs', 'axle-position' => 'front', 'material' => 'metal', 'standard' => 'ece-r90'], ['part-number' => 'BD-206-F', 'oem-number' => '4249-86', 'weight' => 4.8, 'warranty-months' => 12]],
            ['brake-caliper-pride', 'کالیپر ترمز جلو پراید', 'CAR-BRK-PRD-C', 'brake-calipers', 2350000, 5, ['brand' => 'isaco', 'axle-position' => 'front', 'installation-side' => 'left'], ['part-number' => 'BC-PRD-L', 'weight' => 2.6, 'warranty-months' => 6]],
            ['oil-filter-pride', 'فیلتر روغن پراید', 'CAR-FLT-PRD-O', 'oil-filters', 165000, 42, ['brand' => 'bosch', 'vehicle-type' => 'passenger', 'part-condition' => 'new'], ['part-number' => 'OF-PRD-01', 'oem-number' => '15208-65F00', 'warranty-months' => 3]],
            ['air-filter-peugeot-pars', 'فیلتر هوا پژو پارس', 'CAR-FLT-PRS-A', 'air-filters', 210000, 34, ['brand' => 'bosch', 'vehicle-type' => 'passenger', 'part-condition' => 'new'], ['part-number' => 'AF-PRS-01', 'warranty-months' => 3]],
            ['fuel-filter-peugeot-206', 'فیلتر بنزین پژو 206', 'CAR-FLT-206-F', 'fuel-filters', 185000, 27, ['brand' => 'denso', 'vehicle-type' => 'passenger', 'fuel-type' => 'gasoline'], ['part-number' => 'FF-206-01', 'warranty-months' => 3]],
            ['cabin-filter-peugeot-207', 'فیلتر کابین پژو 207', 'CAR-FLT-207-C', 'cabin-filters', 245000, 19, ['brand' => 'valeo', 'vehicle-type' => 'passenger'], ['part-number' => 'CF-207-01', 'warranty-months' => 3]],
            ['spark-plug-ngk-tu5', 'شمع موتور NGK مناسب TU5', 'CAR-ENG-TU5-SP', 'engine-internal-parts', 420000, 30, ['brand' => 'ngk', 'fuel-type' => 'gasoline', 'vehicle-type' => 'passenger'], ['part-number' => 'BKR6EK', 'engine-code' => 'TU5', 'warranty-months' => 6]],
            ['timing-belt-peugeot-206', 'تسمه تایم پژو 206', 'CAR-ENG-206-TB', 'belts-and-chains', 680000, 16, ['brand' => 'bosch', 'vehicle-type' => 'passenger'], ['part-number' => 'TB-206-104', 'engine-code' => 'TU3', 'warranty-months' => 12]],
            ['alternator-belt-pride', 'تسمه دینام پراید', 'CAR-ENG-PRD-AB', 'belts-and-chains', 295000, 25, ['brand' => 'bosch', 'vehicle-type' => 'passenger'], ['part-number' => 'AB-PRD-4PK', 'warranty-months' => 6]],
            ['front-shock-absorber-peugeot-206', 'کمک‌فنر جلو پژو 206', 'CAR-SUS-206-FS', 'shock-absorbers', 1650000, 11, ['brand' => 'sachs', 'axle-position' => 'front', 'installation-side' => 'front'], ['part-number' => 'SA-206-F', 'weight' => 3.7, 'warranty-months' => 12]],
            ['ball-joint-peugeot-405', 'سیبک فرمان پژو 405', 'CAR-SUS-405-BJ', 'ball-joints', 485000, 21, ['brand' => 'skf', 'axle-position' => 'front', 'installation-side' => 'left'], ['part-number' => 'BJ-405-L', 'weight' => 0.9, 'warranty-months' => 6]],
            ['control-arm-peugeot-206', 'طبق جلو پژو 206', 'CAR-SUS-206-CA', 'control-arms', 1250000, 8, ['brand' => 'crouse', 'axle-position' => 'front', 'installation-side' => 'right'], ['part-number' => 'CA-206-R', 'weight' => 3.2, 'warranty-months' => 12]],
            ['oxygen-sensor-peugeot-206', 'سنسور اکسیژن پژو 206', 'CAR-ELC-206-OS', 'sensors', 1850000, 7, ['brand' => 'bosch', 'voltage' => '12v', 'vehicle-type' => 'passenger'], ['part-number' => 'LSF-4.2', 'engine-code' => 'TU5', 'warranty-months' => 6]],
            ['coolant-temperature-sensor-pride', 'سنسور دمای آب پراید', 'CAR-ELC-PRD-CTS', 'sensors', 365000, 18, ['brand' => 'isaco', 'voltage' => '12v', 'vehicle-type' => 'passenger'], ['part-number' => 'CTS-PRD-01', 'warranty-months' => 6]],
            ['battery-60ah', 'باتری خودرو 60 آمپر', 'CAR-ELC-BAT-60', 'batteries', 3550000, 14, ['brand' => 'mando', 'voltage' => '12v', 'amperage' => '60ah'], ['part-number' => 'BAT-60-12', 'weight' => 15.2, 'warranty-months' => 18]],
            ['alternator-peugeot-206', 'دینام پژو 206', 'CAR-ELC-206-ALT', 'alternators', 4950000, 4, ['brand' => 'valeo', 'voltage' => '12v', 'amperage' => '60ah'], ['part-number' => 'ALT-206-12', 'warranty-months' => 12]],
            ['starter-pride', 'استارت پراید', 'CAR-ELC-PRD-STR', 'starters', 3850000, 6, ['brand' => 'isaco', 'voltage' => '12v'], ['part-number' => 'STR-PRD-12', 'warranty-months' => 12]],
            ['headlight-peugeot-206-left', 'چراغ جلو چپ پژو 206', 'CAR-BDY-206-HL', 'lights', 2150000, 10, ['brand' => 'crouse', 'installation-side' => 'left', 'part-condition' => 'new'], ['part-number' => 'HL-206-L', 'warranty-months' => 6]],
            ['ac-compressor-peugeot-405', 'کمپرسور کولر پژو 405', 'CAR-AC-405-CMP', 'ac-compressors', 8900000, 3, ['brand' => 'denso', 'vehicle-type' => 'passenger'], ['part-number' => 'AC-405-01', 'weight' => 6.4, 'warranty-months' => 12]],
            ['radiator-pride', 'رادیاتور پراید', 'CAR-ENG-PRD-RAD', 'cooling-system', 2450000, 8, ['brand' => 'crouse', 'vehicle-type' => 'passenger'], ['part-number' => 'RAD-PRD-01', 'weight' => 3.9, 'warranty-months' => 12]],
            ['water-pump-peugeot-206', 'واترپمپ پژو 206', 'CAR-ENG-206-WP', 'cooling-system', 975000, 15, ['brand' => 'sachs', 'vehicle-type' => 'passenger'], ['part-number' => 'WP-206-01', 'engine-code' => 'TU3', 'warranty-months' => 12]],
            ['clutch-kit-peugeot-405', 'کیت کلاچ پژو 405', 'CAR-PWR-405-CK', 'clutch', 5900000, 5, ['brand' => 'valeo', 'vehicle-type' => 'passenger'], ['part-number' => 'CK-405-01', 'warranty-months' => 12]],
            ['drive-shaft-peugeot-206', 'پلوس کامل پژو 206', 'CAR-PWR-206-DS', 'drive-shafts', 3650000, 7, ['brand' => 'isaco', 'installation-side' => 'right', 'vehicle-type' => 'passenger'], ['part-number' => 'DS-206-R', 'weight' => 5.5, 'warranty-months' => 12]],
        ];

        foreach ($products as $sortOrder => [$slug, $name, $sku, $categorySlug, $price, $stock, $values, $custom]) {
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'sku' => $sku,
                    'short_description' => "{$name} با کیفیت مناسب خودروهای داخلی.",
                    'description' => "{$name} نمونه‌ای از قطعات مصرفی و یدکی خودرو با مشخصات فنی ثبت‌شده.",
                    'price' => $price,
                    'stock' => $stock,
                    'is_active' => true,
                    'is_featured' => $sortOrder < 6,
                    'published_at' => now(),
                    'sort_order' => $sortOrder,
                ]
            );

            $product->categories()->sync([
                Category::where('slug', $categorySlug)->firstOrFail()->id,
            ]);

            $this->syncOptionValues($product, $values + [
                'country-of-origin' => 'iran',
                'part-condition' => 'new',
            ]);
            $this->syncCustomValues($product, $custom);
        }
    }

    /** @param array<string, string> $values */
    private function syncOptionValues(Product $product, array $values): void
    {
        $syncData = [];

        foreach ($values as $attributeSlug => $value) {
            $attributeValue = AttributeValue::whereHas(
                'attribute',
                fn ($query) => $query->where('slug', $attributeSlug)
            )->where('value', $value)->firstOrFail();

            $syncData[$attributeValue->id] = [
                'attribute_id' => $attributeValue->attribute_id,
            ];
        }

        $product->attributeValues()->sync($syncData);
    }

    /** @param array<string, string|float|int> $values */
    private function syncCustomValues(Product $product, array $values): void
    {
        foreach ($values as $attributeSlug => $value) {
            $attribute = Attribute::where('slug', $attributeSlug)->firstOrFail();

            ProductCustomAttributeValue::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'attribute_id' => $attribute->id,
                ],
                [
                    'value_type' => $attribute->type,
                    'value_number' => $attribute->type === 'number' ? $value : null,
                    'value_boolean' => null,
                    'value_text' => $attribute->type === 'text' ? $value : null,
                ]
            );
        }
    }
}
