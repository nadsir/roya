<?php

namespace Database\Seeders;

use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | مانتو
        |--------------------------------------------------------------------------
        */

        $mantos = Category::where('slug', 'mantos')->firstOrFail();

        $manto = Product::updateOrCreate(
            ['slug' => 'classic-black-manto'],
            [
                'name' => 'مانتو کلاسیک مشکی',
                'sku' => 'ROYA-MANTO-001',
                'short_description' => 'مانتوی کلاسیک زنانه با طراحی مینیمال',
                'description' => 'مانتوی زنانه مناسب استفاده روزمره و رسمی.',
                'price' => 2890000,
                'compare_at_price' => 3290000,
                'is_active' => true,
                'is_featured' => true,
                'published_at' => now(),
                'sort_order' => 1,
            ]
        );

        $manto->categories()->sync([
            $mantos->id,
        ]);

        $this->attachAttributes($manto, [
            'color' => ['black'],
            'size' => ['M', 'L'],
            'material' => ['crepe'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | شومیز
        |--------------------------------------------------------------------------
        */

        $shirts = Category::where('slug', 'shirts')->firstOrFail();

        $shirt = Product::updateOrCreate(
            ['slug' => 'white-satin-shirt'],
            [
                'name' => 'شومیز ساتن سفید',
                'sku' => 'ROYA-SHIRT-001',
                'short_description' => 'شومیز ساتن زنانه با طراحی ظریف',
                'description' => 'شومیز ساتن مناسب استایل روزمره و مهمانی.',
                'price' => 1490000,
                'compare_at_price' => 1690000,
                'is_active' => true,
                'is_featured' => true,
                'published_at' => now(),
                'sort_order' => 2,
            ]
        );

        $shirt->categories()->sync([
            $shirts->id,
        ]);

        $this->attachAttributes($shirt, [
            'color' => ['white'],
            'size' => ['S', 'M', 'L'],
            'material' => ['satin'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | کیف
        |--------------------------------------------------------------------------
        */

        $handbags = Category::where('slug', 'handbags')->firstOrFail();

        $bag = Product::updateOrCreate(
            ['slug' => 'classic-leather-handbag'],
            [
                'name' => 'کیف دستی چرم کلاسیک',
                'sku' => 'ROYA-BAG-001',
                'short_description' => 'کیف دستی زنانه از چرم مصنوعی باکیفیت',
                'description' => 'کیف دستی مناسب استفاده روزمره و استایل رسمی.',
                'price' => 2190000,
                'compare_at_price' => 2490000,
                'is_active' => true,
                'is_featured' => true,
                'published_at' => now(),
                'sort_order' => 3,
            ]
        );

        $bag->categories()->sync([
            $handbags->id,
        ]);

        $this->attachAttributes($bag, [
            'color' => ['black'],
            'material' => ['leather'],
            'bag-type' => ['handbag'],
        ]);


        /*
        |--------------------------------------------------------------------------
        | کفش
        |--------------------------------------------------------------------------
        */

        $womenShoes = Category::where('slug', 'women-shoes')->firstOrFail();

        $shoe = Product::updateOrCreate(
            ['slug' => 'classic-womens-shoes'],
            [
                'name' => 'کفش زنانه کلاسیک',
                'sku' => 'ROYA-SHOE-001',
                'short_description' => 'کفش زنانه کلاسیک مناسب استفاده روزمره',
                'description' => 'کفش زنانه با طراحی ساده و شیک.',
                'price' => 2390000,
                'compare_at_price' => 2690000,
                'is_active' => true,
                'is_featured' => false,
                'published_at' => now(),
                'sort_order' => 4,
            ]
        );

        $shoe->categories()->sync([
            $womenShoes->id,
        ]);

$this->attachAttributes($shoe, [
    'color' => ['black'],
    'shoe-size' => ['38', '39'],
    'material' => ['leather'],
    'shoe-type' => ['formal'],
]);


        /*
        |--------------------------------------------------------------------------
        | عینک آفتابی
        |--------------------------------------------------------------------------
        */

        $sunglasses = Category::where('slug', 'sunglasses')->firstOrFail();

        $glasses = Product::updateOrCreate(
            ['slug' => 'classic-black-sunglasses'],
            [
                'name' => 'عینک آفتابی کلاسیک مشکی',
                'sku' => 'ROYA-GLASS-001',
                'short_description' => 'عینک آفتابی زنانه با فریم کلاسیک',
                'description' => 'عینک آفتابی زنانه مناسب استفاده روزمره.',
                'price' => 1890000,
                'compare_at_price' => 2190000,
                'is_active' => true,
                'is_featured' => true,
                'published_at' => now(),
                'sort_order' => 5,
            ]
        );

        $glasses->categories()->sync([
            $sunglasses->id,
        ]);

        $this->attachAttributes($glasses, [
            'color' => ['black'],
            'frame-material' => ['acetate'],
            'frame-shape' => ['round'],
        ]);
    }


    /**
     * اتصال مقادیر ویژگی‌ها به محصول
     */
private function attachAttributes(
    Product $product,
    array $attributes
): void {
    $syncData = [];

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

            $syncData[$attributeValue->id] = [
                'attribute_id' => $attributeValue->attribute_id,
            ];
        }
    }

    $product->attributeValues()->sync($syncData);
}
}