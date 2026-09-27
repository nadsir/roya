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
            'brand' => ['Bosch' => 'bosch', 'Valeo' => 'valeo', 'Denso' => 'denso', 'NGK' => 'ngk', 'SKF' => 'skf', 'Sachs' => 'sachs', 'Mando' => 'mando', 'ایساکو' => 'isaco', 'کروز' => 'crouse'],
            'country-of-origin' => ['ایران' => 'iran', 'آلمان' => 'germany', 'فرانسه' => 'france', 'ژاپن' => 'japan', 'کره جنوبی' => 'south-korea', 'چین' => 'china'],
            'part-condition' => ['نو' => 'new', 'استوک' => 'used'],
            'installation-side' => ['چپ' => 'left', 'راست' => 'right', 'جلو' => 'front', 'عقب' => 'rear'],
            'axle-position' => ['جلو' => 'front', 'عقب' => 'rear'],
            'material' => ['سرامیکی' => 'ceramic', 'نیمه‌فلزی' => 'semi-metallic', 'فلزی' => 'metal', 'آلومینیوم' => 'aluminum', 'پلاستیک' => 'plastic', 'لاستیک' => 'rubber'],
            'vehicle-type' => ['سواری' => 'passenger', 'وانت' => 'pickup', 'شاسی‌بلند' => 'suv', 'تجاری سبک' => 'light-commercial'],
            'fuel-type' => ['بنزینی' => 'gasoline', 'دیزلی' => 'diesel', 'دوگانه‌سوز' => 'bi-fuel'],
            'voltage' => ['12V' => '12v', '24V' => '24v'],
            'amperage' => ['45Ah' => '45ah', '55Ah' => '55ah', '60Ah' => '60ah', '66Ah' => '66ah', '74Ah' => '74ah'],
            'standard' => ['ISO 9001' => 'iso-9001', 'IATF 16949' => 'iatf-16949', 'ECE R90' => 'ece-r90', 'ایران‌کد' => 'iran-code'],
        ];

        foreach ($values as $attributeSlug => $items) {
            $attribute = Attribute::where('slug', $attributeSlug)->firstOrFail();

            foreach ($items as $label => $value) {
                AttributeValue::updateOrCreate(
                    [
                        'attribute_id' => $attribute->id,
                        'value' => $value,
                    ],
                    [
                        'label' => $label,
                        'hex_color' => null,
                        'sort_order' => array_search($label, array_keys($items), true),
                    ]
                );
            }
        }
    }
}
