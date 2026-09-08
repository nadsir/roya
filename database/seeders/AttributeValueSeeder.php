<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;

class AttributeValueSeeder extends Seeder
{
    public function run(): void
    {
        $values = [

            /*
             * رنگ
             */
            'color' => [
                ['label' => 'مشکی', 'value' => 'black', 'hex_color' => '#000000'],
                ['label' => 'سفید', 'value' => 'white', 'hex_color' => '#FFFFFF'],
                ['label' => 'قرمز', 'value' => 'red', 'hex_color' => '#EF4444'],
                ['label' => 'صورتی', 'value' => 'pink', 'hex_color' => '#EC4899'],
                ['label' => 'کرم', 'value' => 'cream', 'hex_color' => '#F5E6D3'],
                ['label' => 'قهوه‌ای', 'value' => 'brown', 'hex_color' => '#92400E'],
                ['label' => 'بژ', 'value' => 'beige', 'hex_color' => '#D6C2A1'],
                ['label' => 'آبی', 'value' => 'blue', 'hex_color' => '#3B82F6'],
                ['label' => 'سبز', 'value' => 'green', 'hex_color' => '#22C55E'],
                ['label' => 'طلایی', 'value' => 'gold', 'hex_color' => '#D4AF37'],
                ['label' => 'نقره‌ای', 'value' => 'silver', 'hex_color' => '#C0C0C0'],
            ],

            /*
             * سایز
             */
            'size' => [
                ['label' => 'XS', 'value' => 'xs'],
                ['label' => 'S', 'value' => 's'],
                ['label' => 'M', 'value' => 'm'],
                ['label' => 'L', 'value' => 'l'],
                ['label' => 'XL', 'value' => 'xl'],
                ['label' => 'XXL', 'value' => 'xxl'],
            ],
            'shoe-size' => [
    ['label' => '36', 'value' => '36'],
    ['label' => '37', 'value' => '37'],
    ['label' => '38', 'value' => '38'],
    ['label' => '39', 'value' => '39'],
    ['label' => '40', 'value' => '40'],
    ['label' => '41', 'value' => '41'],
    ['label' => '42', 'value' => '42'],
],

            /*
             * جنس
             */
            'material' => [
                ['label' => 'نخ', 'value' => 'cotton'],
                ['label' => 'کتان', 'value' => 'linen'],
                ['label' => 'لینن', 'value' => 'linen-fabric'],
                ['label' => 'پلی‌استر', 'value' => 'polyester'],
                ['label' => 'چرم', 'value' => 'leather'],
                ['label' => 'جیر', 'value' => 'suede'],
                ['label' => 'ساتن', 'value' => 'satin'],
                ['label' => 'ابریشم', 'value' => 'silk'],
                [
    'label' => 'کرپ',
    'value' => 'crepe',
],
            ],

            /*
             * نوع کیف
             */
            'bag-type' => [
                ['label' => 'دستی', 'value' => 'handbag'],
                ['label' => 'دوشی', 'value' => 'shoulder-bag'],
                ['label' => 'کراس‌بادی', 'value' => 'crossbody'],
                ['label' => 'کلاچ', 'value' => 'clutch'],
                ['label' => 'کوله‌پشتی', 'value' => 'backpack'],
                ['label' => 'کیف کمری', 'value' => 'waist-bag'],
            ],

            /*
             * نوع کفش
             */
            'shoe-type' => [
                ['label' => 'کفش روزمره', 'value' => 'casual'],
                ['label' => 'کفش مجلسی', 'value' => 'formal'],
                ['label' => 'صندل', 'value' => 'sandal'],
                ['label' => 'بوت', 'value' => 'boots'],
                ['label' => 'نیم‌بوت', 'value' => 'ankle-boots'],
                ['label' => 'کتانی', 'value' => 'sneakers'],
                ['label' => 'لوفر', 'value' => 'loafers'],
            ],

            /*
             * جنس فریم
             */
            'frame-material' => [
                ['label' => 'فلزی', 'value' => 'metal'],
                ['label' => 'استیل', 'value' => 'steel'],
                ['label' => 'استات', 'value' => 'acetate'],
                ['label' => 'پلاستیک', 'value' => 'plastic'],
                ['label' => 'تیتانیوم', 'value' => 'titanium'],
            ],

            /*
             * شکل فریم
             */
            'frame-shape' => [
                ['label' => 'گرد', 'value' => 'round'],
                ['label' => 'مربعی', 'value' => 'square'],
                ['label' => 'مستطیلی', 'value' => 'rectangle'],
                ['label' => 'بیضی', 'value' => 'oval'],
                ['label' => 'خلبانی', 'value' => 'aviator'],
                ['label' => 'گربه‌ای', 'value' => 'cat-eye'],
                ['label' => 'پروانه‌ای', 'value' => 'butterfly'],
            ],
        ];

        foreach ($values as $attributeSlug => $attributeValues) {

            $attribute = Attribute::where(
                'slug',
                $attributeSlug
            )->first();

            if (!$attribute) {
                continue;
            }

            foreach ($attributeValues as $index => $valueData) {

                AttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                        'value' => $valueData['value'],
                    ],
                    [
                        'label' => $valueData['label'],
                        'hex_color' => $valueData['hex_color'] ?? null,
                        'sort_order' => $index,
                    ]
                );
            }
        }
    }
}