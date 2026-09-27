<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VariantFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }

    private function categoryWithBrandAxis(): Category
    {
        $category = Category::create([
            'name' => 'Brakes',
            'slug' => 'brakes',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        return $category;
    }

    private function createBrandValues(Attribute $brand): array
    {
        return [
            AttributeValue::create([
                'attribute_id' => $brand->id,
                'label' => 'Brembo',
                'value' => 'brembo',
            ]),
            AttributeValue::create([
                'attribute_id' => $brand->id,
                'label' => 'Bosch',
                'value' => 'bosch',
            ]),
            AttributeValue::create([
                'attribute_id' => $brand->id,
                'label' => 'TRW',
                'value' => 'trw',
            ]),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | EFFECTIVE VARIANT PRICE
    |--------------------------------------------------------------------------
    */

    public function test_variant_with_explicit_price_returns_own_price(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => 150,
            'stock' => 5,
            'combination_key' => '1',
        ]);

        $this->assertEquals(150.0, $variant->effective_price);
    }

    public function test_variant_with_null_price_falls_back_to_product_price(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => null,
            'stock' => 5,
            'combination_key' => '1',
        ]);

        $this->assertEquals(100.0, $variant->effective_price);
    }

    public function test_variant_price_is_exposed_in_api_response(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'is_active' => true,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => null,
            'stock' => 5,
            'is_active' => true,
            'combination_key' => '1',
        ]);

        $response = $this->getJson('/api/products')
            ->assertOk();

        $variantData = collect($response->json('data'))
            ->firstWhere('slug', 'brake-pad')['variants'][0];

        $this->assertEquals(100.0, $variantData['price']);
    }

    /*
    |--------------------------------------------------------------------------
    | EFFECTIVE VARIANT COMPARE PRICE
    |--------------------------------------------------------------------------
    */

    public function test_variant_with_explicit_compare_at_price_returns_own_value(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'compare_at_price' => 200,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => 150,
            'compare_at_price' => 180,
            'stock' => 5,
            'combination_key' => '1',
        ]);

        $this->assertEquals(180.0, $variant->effective_compare_at_price);
    }

    public function test_variant_with_null_compare_at_price_falls_back_to_product(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'compare_at_price' => 200,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => 150,
            'compare_at_price' => null,
            'stock' => 5,
            'combination_key' => '1',
        ]);

        $this->assertEquals(200.0, $variant->effective_compare_at_price);
    }

    public function test_product_compare_at_price_null_remains_null(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'compare_at_price' => null,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => 150,
            'compare_at_price' => null,
            'stock' => 5,
            'combination_key' => '1',
        ]);

        $this->assertNull($variant->effective_compare_at_price);
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK RULE
    |--------------------------------------------------------------------------
    */

    public function test_no_variants_uses_product_stock(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 10,
        ]);

        $this->assertTrue($product->in_stock);
    }

    public function test_zero_product_stock_means_unavailable(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 0,
        ]);

        $this->assertFalse($product->in_stock);
    }

    public function test_active_variants_stock_is_aggregated(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 0,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 50,
            'stock' => 5,
            'is_active' => true,
            'combination_key' => '1',
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 60,
            'stock' => 3,
            'is_active' => true,
            'combination_key' => '2',
        ]);

        $this->assertTrue($product->fresh()->in_stock);
    }

    public function test_inactive_variants_excluded_from_stock(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 0,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 50,
            'stock' => 5,
            'is_active' => false,
            'combination_key' => '1',
        ]);

        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_zero_total_variant_stock_means_unavailable(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 0,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 50,
            'stock' => 0,
            'is_active' => true,
            'combination_key' => '1',
        ]);

        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_in_stock_exposed_in_api_response(): void
    {
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'stock' => 0,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 50,
            'stock' => 3,
            'is_active' => true,
            'combination_key' => '1',
        ]);

        $response = $this->getJson('/api/products')
            ->assertOk();

        $productData = collect($response->json('data'))
            ->firstWhere('slug', 'brake-pad');

        $this->assertTrue($productData['in_stock']);
    }

    /*
    |--------------------------------------------------------------------------
    | SKU UNIQUENESS
    |--------------------------------------------------------------------------
    */

    public function test_duplicate_sku_across_different_products_is_rejected(): void
    {
        $this->authenticate();

        $category = $this->categoryWithBrandAxis();
        [$brembo, $bosch] = $this->createBrandValues(
            Attribute::where('slug', 'brand')->first()
        );

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad A',
            'slug' => 'brake-pad-a',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'sku' => 'BP-001',
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id],
            ]],
        ])->assertCreated();

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad B',
            'slug' => 'brake-pad-b',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'sku' => 'BP-001',
                'stock' => 5,
                'attribute_value_ids' => [$bosch->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants');
    }

    public function test_duplicate_sku_between_variants_in_same_request_is_rejected(): void
    {
        $this->authenticate();

        $category = $this->categoryWithBrandAxis();
        [$brembo, $bosch] = $this->createBrandValues(
            Attribute::where('slug', 'brand')->first()
        );

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                [
                    'sku' => 'BP-001',
                    'stock' => 5,
                    'attribute_value_ids' => [$brembo->id],
                ],
                [
                    'sku' => 'BP-001',
                    'stock' => 3,
                    'attribute_value_ids' => [$bosch->id],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants');
    }

    public function test_updating_variant_while_keepings_own_sku_succeeds(): void
    {
        $this->authenticate();

        $category = $this->categoryWithBrandAxis();
        [$brembo] = $this->createBrandValues(
            Attribute::where('slug', 'brand')->first()
        );

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'sku' => 'BP-001',
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id],
            ]],
        ])->assertCreated();

        $productId = $response->json('id');

        $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 120,
            'category_ids' => [$category->id],
            'variants' => [[
                'sku' => 'BP-001',
                'stock' => 10,
                'attribute_value_ids' => [$brembo->id],
            ]],
        ])->assertOk();
    }

    public function test_updating_variant_to_another_variants_sku_fails(): void
    {
        $this->authenticate();

        $category = $this->categoryWithBrandAxis();
        [$brembo, $bosch] = $this->createBrandValues(
            Attribute::where('slug', 'brand')->first()
        );

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                [
                    'sku' => 'BP-001',
                    'stock' => 5,
                    'attribute_value_ids' => [$brembo->id],
                ],
                [
                    'sku' => 'BP-002',
                    'stock' => 3,
                    'attribute_value_ids' => [$bosch->id],
                ],
            ],
        ])->assertCreated();

        $productId = $response->json('id');

        $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                [
                    'sku' => 'BP-001',
                    'stock' => 5,
                    'attribute_value_ids' => [$brembo->id],
                ],
                [
                    'sku' => 'BP-001',
                    'stock' => 3,
                    'attribute_value_ids' => [$bosch->id],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants');

        $variantA = ProductVariant::where('product_id', $productId)
            ->where('combination_key', $brembo->id)
            ->first();
        $this->assertEquals('BP-001', $variantA->sku);
    }

    public function test_null_sku_variants_are_allowed(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'variants' => [
                [
                    'stock' => 5,
                ],
                [
                    'stock' => 3,
                ],
            ],
        ])->assertCreated();
    }
}
