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
        /*
        |--------------------------------------------------------------------------
        | مانتو
        |--------------------------------------------------------------------------
        */

        $manto = Product::where(
            'slug',
            'classic-black-manto'
        )->firstOrFail();

        $this->createVariant(
            $manto,
            [
                'size' => ['m'],
            ],
            5
        );

        $this->createVariant(
            $manto,
            [
                'size' => ['l'],
            ],
            3
        );


        /*
        |--------------------------------------------------------------------------
        | شومیز
        |--------------------------------------------------------------------------
        */

        $shirt = Product::where(
            'slug',
            'white-satin-shirt'
        )->firstOrFail();

        $this->createVariant(
            $shirt,
            [
                'size' => ['s'],
            ],
            4
        );

        $this->createVariant(
            $shirt,
            [
                'size' => ['m'],
            ],
            6
        );

        $this->createVariant(
            $shirt,
            [
                'size' => ['l'],
            ],
            3
        );


        /*
        |--------------------------------------------------------------------------
        | کفش
        |--------------------------------------------------------------------------
        */

        $shoe = Product::where(
            'slug',
            'classic-womens-shoes'
        )->firstOrFail();

        $this->createVariant(
            $shoe,
            [
                'shoe-size' => ['38'],
            ],
            4
        );

        $this->createVariant(
            $shoe,
            [
                'shoe-size' => ['39'],
            ],
            2
        );
    }


    /**
     * ساخت Variant
     */
    private function createVariant(
        Product $product,
        array $attributes,
        int $stock
    ): ProductVariant {

        $attributeValueIds = [];

        foreach ($attributes as $attributeSlug => $values) {

            foreach ($values as $value) {

                $attributeValue = AttributeValue::whereHas(
                    'attribute',
                    function ($query) use ($attributeSlug) {
                        $query->where('slug', $attributeSlug);
                    }
                )
                    ->where('value', $value)
                    ->firstOrFail();

                $attributeValueIds[] = $attributeValue->id;
            }
        }

        /*
         * مرتب‌سازی برای ساخت combination_key ثابت
         */
        sort($attributeValueIds);

        $combinationKey = implode('-', $attributeValueIds);

        $variant = ProductVariant::updateOrCreate(
            [
                'product_id' => $product->id,
                'combination_key' => $combinationKey,
            ],
            [
                'sku' => $product->sku . '-' . strtoupper(
                    str_replace('-', '', $combinationKey)
                ),
                'price' => null,
                'compare_at_price' => null,
                'stock' => $stock,
                'is_active' => true,
            ]
        );

        /*
         * اتصال Attribute Valueها به Variant
         */
        $variant->attributeValues()->sync(
            $attributeValueIds
        );

        return $variant;
    }
}