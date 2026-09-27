<?php

namespace Database\Seeders;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $this->createVariant('front-brake-pad-peugeot-405', ['axle-position' => 'front'], 11);
        $this->createVariant('front-brake-pad-peugeot-405', ['axle-position' => 'rear'], 7);
        $this->createVariant('battery-60ah', ['voltage' => '12v', 'amperage' => '60ah'], 10);
        $this->createVariant('battery-60ah', ['voltage' => '12v', 'amperage' => '66ah'], 4);
    }

    /** @param array<string, string> $values */
    private function createVariant(string $productSlug, array $values, int $stock): void
    {
        $product = Product::where('slug', $productSlug)->firstOrFail();
        $attributeValueIds = [];

        foreach ($values as $attributeSlug => $value) {
            $attributeValueIds[] = AttributeValue::whereHas(
                'attribute',
                fn ($query) => $query->where('slug', $attributeSlug)
            )->where('value', $value)->firstOrFail()->id;
        }

        sort($attributeValueIds);
        $combinationKey = implode('-', $attributeValueIds);

        $variant = ProductVariant::updateOrCreate(
            [
                'product_id' => $product->id,
                'combination_key' => $combinationKey,
            ],
            [
                'sku' => $product->sku . '-V' . strtoupper(str_replace('-', '', $combinationKey)),
                'stock' => $stock,
                'is_active' => true,
            ]
        );

        $variant->attributeValues()->sync($attributeValueIds);
    }
}
