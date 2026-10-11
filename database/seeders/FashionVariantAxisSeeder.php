<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FashionVariantAxisSeeder extends Seeder
{
    /**
     * Variant axes to enable per fashion category slug.
     *
     * Only attributes that genuinely represent a selectable product variant are
     * listed here. Type variants (bag-type / shoe-type / frame-shape) and material
     * style attributes stay filterable-only. This seeder never touches the
     * unrelated automotive/legacy taxonomy.
     *
     * @var array<string, list<string>>
     */
    private const AXES = [
        'clothing' => ['color', 'size'],
        'mantos' => ['color', 'size'],
        'shirts' => ['color', 'size'],
        'evening-dresses' => ['color', 'size'],
        'casual-dresses' => ['color', 'size'],
        'bags' => ['color'],
        'handbags' => ['color'],
        'shoulder-bags' => ['color'],
        'evening-bags' => ['color'],
        'shoes' => ['color', 'shoe-size'],
        'women-shoes' => ['color', 'shoe-size'],
        'sandals' => ['color', 'shoe-size'],
        'boots' => ['color', 'shoe-size'],
        'glasses' => ['color'],
        'optical-glasses' => ['color'],
        'sunglasses' => ['color'],
        'accessories' => ['color'],
        'jewelry' => ['color'],
        'watches' => ['color'],
        'other-accessories' => ['color'],
    ];

    /** @var list<string> */
    private const SUPPORTED_TYPES = ['select', 'multiselect', 'color'];

    /**
     * Enable the mapped variant axes without resetting any other configuration.
     *
     * The seeder is safe to run repeatedly:
     *  - missing categories / attributes are skipped instead of crashing;
     *   attributes that are disabled for a category stay disabled;
     *  - every other pivot column (is_enabled / is_required / is_filterable /
     *    sort_order) is preserved for rows that already exist.
     */
    public function run(): void
    {
        $enabled = 0;
        $skipped = [];

        foreach (self::AXES as $categorySlug => $attributeSlugs) {
            $category = Category::query()
                ->where('slug', $categorySlug)
                ->first();

            if ($category === null) {
                $skipped[] = "category:{$categorySlug}";

                continue;
            }

            foreach ($attributeSlugs as $attributeSlug) {
                $attribute = Attribute::query()
                    ->where('slug', $attributeSlug)
                    ->first();

                if ($attribute === null) {
                    $skipped[] = "attribute:{$attributeSlug}";

                    continue;
                }

                if (! in_array($attribute->type, self::SUPPORTED_TYPES, true)) {
                    $skipped[] = "{$categorySlug}.{$attributeSlug} (type {$attribute->type})";

                    continue;
                }

                if ($attribute->values()->doesntExist()) {
                    $skipped[] = "{$categorySlug}.{$attributeSlug} (no values)";

                    continue;
                }

                $existing = DB::table('category_attributes')
                    ->where('category_id', $category->id)
                    ->where('attribute_id', $attribute->id)
                    ->first();

                if ($existing === null) {
                    DB::table('category_attributes')->insert([
                        'category_id' => $category->id,
                        'attribute_id' => $attribute->id,
                        'is_enabled' => true,
                        'is_required' => (bool) $attribute->is_required,
                        'is_filterable' => (bool) $attribute->is_filterable,
                        'is_variant_axis' => true,
                        'sort_order' => (int) $attribute->sort_order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $enabled++;

                    continue;
                }

                if (! $existing->is_enabled) {
                    $skipped[] = "{$categorySlug}.{$attributeSlug} (disabled)";

                    continue;
                }

                if ((bool) $existing->is_variant_axis) {
                    continue;
                }

                DB::table('category_attributes')
                    ->where('id', $existing->id)
                    ->update([
                        'is_variant_axis' => true,
                        'updated_at' => now(),
                    ]);

                $enabled++;
            }
        }

        $this->command?->info("Fashion variant axes enabled: {$enabled}");

        if ($skipped !== []) {
            $this->command?->warn('Skipped: ' . implode(', ', $skipped));
        }
    }
}
