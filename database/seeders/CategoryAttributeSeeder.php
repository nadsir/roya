<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryAttributeSeeder extends Seeder
{
    public function run(): void
    {
        $this->sync('car-parts', [
            'brand' => [false, true, false],
            'country-of-origin' => [false, true, false],
            'part-condition' => [false, true, false],
            'part-number' => [false, false, false],
            'oem-number' => [false, false, false],
            'installation-side' => [false, false, false],
            'vehicle-type' => [false, true, false],
            'weight' => [false, false, false],
            'warranty-months' => [false, false, false],
        ]);

        $this->sync('engine', [
            'vehicle-type' => [false, true, false],
            'fuel-type' => [false, true, false],
            'engine-code' => [false, false, false],
            'weight' => [false, false, false],
        ]);

        $this->sync('brake-system', [
            'axle-position' => [true, true, true],
            'installation-side' => [false, true, false],
            'material' => [false, true, false],
            'standard' => [false, true, false],
        ]);

        $this->sync('brake-pads', [
            // Child configuration intentionally overrides the inherited material filter.
            'material' => [false, false, false],
        ]);

        $this->sync('suspension-and-steering', [
            'axle-position' => [true, true, true],
            'installation-side' => [false, true, false],
            'material' => [false, true, false],
        ]);

        $this->sync('electrical-and-electronics', [
            'voltage' => [true, true, true],
            'amperage' => [false, true, true],
            'engine-code' => [false, false, false],
        ]);

        $this->sync('filters-and-service', [
            'vehicle-type' => [false, true, false],
            'fuel-type' => [false, true, false],
        ]);
    }

    /** @param array<string, array{0: bool, 1: bool, 2: bool}> $configurations */
    private function sync(string $categorySlug, array $configurations): void
    {
        $category = Category::where('slug', $categorySlug)->firstOrFail();

        $syncData = [];

        foreach ($configurations as $attributeSlug => $configuration) {
            $attribute = Attribute::where('slug', $attributeSlug)->firstOrFail();

            $syncData[$attribute->id] = [
                'is_required' => $configuration[0],
                'is_filterable' => $configuration[1],
                'is_variant_axis' => $configuration[2],
                'sort_order' => count($syncData),
            ];
        }

        $category->attributes()->sync($syncData);
    }
}
