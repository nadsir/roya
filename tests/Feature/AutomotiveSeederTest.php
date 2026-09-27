<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\EffectiveCategoryAttributesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomotiveSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_automotive_data_without_fashion_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('categories', ['slug' => 'car-parts']);
        $this->assertDatabaseHas('categories', ['slug' => 'brake-pads']);
        $this->assertDatabaseMissing('categories', ['slug' => 'clothing']);
        $this->assertDatabaseMissing('attributes', ['slug' => 'shoe-size']);
        $this->assertDatabaseMissing('products', ['slug' => 'white-satin-shirt']);

        $this->assertSame(46, Category::count());
        $this->assertSame(16, Attribute::count());
        $this->assertSame(47, AttributeValue::count());
        $this->assertSame(25, Product::count());
        $this->assertSame(4, ProductVariant::count());
        $this->assertSame(0, ProductImage::count());
    }

    public function test_seeded_brake_filters_are_category_specific_and_dynamic(): void
    {
        $this->seed();

        $this->getJson('/api/categories/brake-pads/filters')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'brand'])
            ->assertJsonFragment(['slug' => 'axle-position'])
            ->assertJsonMissing(['slug' => 'material']);

        $this->getJson('/api/products?category=brake-pads&brand=bosch')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'front-brake-pad-peugeot-405');
    }

    public function test_seeded_product_values_match_their_effective_category_attributes(): void
    {
        $this->seed();
        $resolver = app(EffectiveCategoryAttributesResolver::class);

        Product::query()
            ->with([
                'categories',
                'attributeValues',
                'customAttributeValues',
                'variants.attributeValues',
            ])
            ->each(function (Product $product) use ($resolver): void {
                $allowedAttributeIds = $resolver
                    ->for($product->categories->sole())
                    ->pluck('id');
                $variantAxisIds = $resolver
                    ->for($product->categories->sole())
                    ->filter(fn (Attribute $attribute) => $attribute->pivot->is_variant_axis)
                    ->pluck('id');

                $this->assertTrue(
                    $product->attributeValues
                        ->pluck('attribute_id')
                        ->diff($allowedAttributeIds)
                        ->isEmpty(),
                    "Option attributes are incompatible for {$product->slug}."
                );
                $this->assertTrue(
                    $product->customAttributeValues
                        ->pluck('attribute_id')
                        ->diff($allowedAttributeIds)
                        ->isEmpty(),
                    "Custom attributes are incompatible for {$product->slug}."
                );

                foreach ($product->variants as $variant) {
                    $this->assertTrue(
                        $variant->attributeValues
                            ->pluck('attribute_id')
                            ->diff($variantAxisIds)
                            ->isEmpty(),
                        "Variant attributes are incompatible for {$product->slug}."
                    );
                }
            });
    }
}
