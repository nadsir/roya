<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class ProductImageSeeder extends Seeder
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

        $this->createImage(
            $manto,
            'products/manto/classic-black-manto-1.jpg',
            'مانتو کلاسیک مشکی',
            true,
            1
        );

        $this->createImage(
            $manto,
            'products/manto/classic-black-manto-2.jpg',
            'نمای پشت مانتو کلاسیک مشکی',
            false,
            2
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

        $this->createImage(
            $shirt,
            'products/shirt/white-satin-shirt-1.jpg',
            'شومیز ساتن سفید',
            true,
            1
        );

        $this->createImage(
            $shirt,
            'products/shirt/white-satin-shirt-2.jpg',
            'نمای شومیز ساتن سفید',
            false,
            2
        );


        /*
        |--------------------------------------------------------------------------
        | کیف
        |--------------------------------------------------------------------------
        */

        $bag = Product::where(
            'slug',
            'classic-leather-handbag'
        )->firstOrFail();

        $this->createImage(
            $bag,
            'products/bag/classic-leather-handbag-1.jpg',
            'کیف دستی چرم کلاسیک',
            true,
            1
        );

        $this->createImage(
            $bag,
            'products/bag/classic-leather-handbag-2.jpg',
            'نمای کیف دستی چرم کلاسیک',
            false,
            2
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

        $this->createImage(
            $shoe,
            'products/shoe/classic-womens-shoes-1.jpg',
            'کفش زنانه کلاسیک',
            true,
            1
        );

        $this->createImage(
            $shoe,
            'products/shoe/classic-womens-shoes-2.jpg',
            'نمای کفش زنانه کلاسیک',
            false,
            2
        );


        /*
        |--------------------------------------------------------------------------
        | عینک
        |--------------------------------------------------------------------------
        */

        $glasses = Product::where(
            'slug',
            'classic-black-sunglasses'
        )->firstOrFail();

        $this->createImage(
            $glasses,
            'products/glasses/classic-black-sunglasses-1.jpg',
            'عینک آفتابی کلاسیک مشکی',
            true,
            1
        );

        $this->createImage(
            $glasses,
            'products/glasses/classic-black-sunglasses-2.jpg',
            'نمای عینک آفتابی کلاسیک مشکی',
            false,
            2
        );
    }


    /**
     * ساخت تصویر محصول
     */
    private function createImage(
        Product $product,
        string $path,
        string $altText,
        bool $isPrimary,
        int $sortOrder
    ): ProductImage {
        return ProductImage::updateOrCreate(
            [
                'product_id' => $product->id,
                'path' => $path,
            ],
            [
                'variant_id' => null,
                'alt_text' => $altText,
                'is_primary' => $isPrimary,
                'sort_order' => $sortOrder,
            ]
        );
    }
}