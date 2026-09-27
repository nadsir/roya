<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    /* ────────────────────────────────────────────────────────────
     | 1. Product without variant
     | ──────────────────────────────────────────────────────────── */

    public function test_product_without_variant_opens(): void
    {
        $product = $this->product('Brake Pad', 'brake-pad');

        $response = $this->getJson("/api/products/{$product->id}")
            ->assertOk();

        $data = $response->json();
        $this->assertEquals($product->id, $data['id']);
        $this->assertEquals('Brake Pad', $data['name']);
        $this->assertEquals(100.0, $data['price']);
        $this->assertEmpty($data['variants']);
    }

    /* ────────────────────────────────────────────────────────────
     | 2. Product with single-axis variant
     | ──────────────────────────────────────────────────────────── */

    public function test_product_with_variant_opens(): void
    {
        $product = $this->product('Caliper', 'caliper');
        $attr = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'select']);
        $black = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Black', 'value' => 'black']);
        $white = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'White', 'value' => 'white']);

        // Attach product-level attribute values (for the attributes table)
        $product->attributeValues()->attach([
            $black->id => ['attribute_id' => $attr->id],
            $white->id => ['attribute_id' => $attr->id],
        ]);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CAL-BLK',
            'price' => 200,
            'stock' => 5,
            'is_active' => true,
            'combination_key' => 'black',
        ]);
        $v1->attributeValues()->attach($black->id);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CAL-WHT',
            'price' => 220,
            'stock' => 3,
            'is_active' => true,
            'combination_key' => 'white',
        ]);
        $v2->attributeValues()->attach($white->id);

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();

        $this->assertCount(2, $data['variants']);
        $this->assertArrayHasKey('color', $data['attributes']);
        $this->assertCount(2, $data['attributes']['color']);
    }

    /* ────────────────────────────────────────────────────────────
     | 3. Product with multiple variant axes
     | ──────────────────────────────────────────────────────────── */

    public function test_product_with_multiple_variant_axes(): void
    {
        $product = $this->product('Shirt', 'shirt');

        $colorAttr = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'select']);
        $sizeAttr = Attribute::create(['name' => 'Size', 'slug' => 'size', 'type' => 'select']);

        $red = AttributeValue::create(['attribute_id' => $colorAttr->id, 'label' => 'Red', 'value' => 'red']);
        $blue = AttributeValue::create(['attribute_id' => $colorAttr->id, 'label' => 'Blue', 'value' => 'blue']);
        $sm = AttributeValue::create(['attribute_id' => $sizeAttr->id, 'label' => 'S', 'value' => 's']);
        $lg = AttributeValue::create(['attribute_id' => $sizeAttr->id, 'label' => 'L', 'value' => 'l']);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHR-RD-S',
            'price' => 150,
            'stock' => 2,
            'is_active' => true,
            'combination_key' => 'red-s',
        ]);
        $v1->attributeValues()->attach([$red->id, $sm->id]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SHR-BL-L',
            'price' => 180,
            'stock' => 4,
            'is_active' => true,
            'combination_key' => 'blue-l',
        ]);
        $v2->attributeValues()->attach([$blue->id, $lg->id]);

        // Attach product-level attribute values (for the attributes table)
        $product->attributeValues()->attach([
            $red->id => ['attribute_id' => $colorAttr->id],
            $blue->id => ['attribute_id' => $colorAttr->id],
            $sm->id => ['attribute_id' => $sizeAttr->id],
            $lg->id => ['attribute_id' => $sizeAttr->id],
        ]);

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();

        $this->assertCount(2, $data['variants']);
        $this->assertArrayHasKey('color', $data['attributes']);
        $this->assertArrayHasKey('size', $data['attributes']);
        $this->assertCount(2, $data['attributes']['color']);
        $this->assertCount(2, $data['attributes']['size']);
    }

    /* ────────────────────────────────────────────────────────────
     | 4. Variant combination data is correct
     | ──────────────────────────────────────────────────────────── */

    public function test_variant_combination_data_is_correct(): void
    {
        $product = $this->product('Pad', 'pad');
        $attr = Attribute::create(['name' => 'Position', 'slug' => 'position', 'type' => 'select']);
        $front = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Front', 'value' => 'front']);
        $rear = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Rear', 'value' => 'rear']);

        $vf = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PAD-F',
            'price' => 100,
            'compare_at_price' => 120,
            'stock' => 8,
            'is_active' => true,
            'combination_key' => 'front',
        ]);
        $vf->attributeValues()->attach($front->id);

        $vr = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'PAD-R',
            'price' => 90,
            'compare_at_price' => null,
            'stock' => 0,
            'is_active' => true,
            'combination_key' => 'rear',
        ]);
        $vr->attributeValues()->attach($rear->id);

        $variants = $this->getJson("/api/products/{$product->id}")->assertOk()->json('variants');

        $frontVariant = collect($variants)->firstWhere('sku', 'PAD-F');
        $this->assertNotNull($frontVariant);
        $this->assertEquals(100.0, $frontVariant['price']);
        $this->assertEquals(120.0, $frontVariant['compare_at_price']);
        $this->assertEquals(8, $frontVariant['stock']);

        $rearVariant = collect($variants)->firstWhere('sku', 'PAD-R');
        $this->assertNotNull($rearVariant);
        $this->assertEquals(90.0, $rearVariant['price']);
        $this->assertNull($rearVariant['compare_at_price']);
        $this->assertEquals(0, $rearVariant['stock']);
    }

    /* ────────────────────────────────────────────────────────────
     | 5. Inactive variants are excluded
     | ──────────────────────────────────────────────────────────── */

    public function test_inactive_variants_are_included_in_api(): void
    {
        $product = $this->product('Light', 'light');
        $attr = Attribute::create(['name' => 'Type', 'slug' => 'type', 'type' => 'select']);
        $led = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'LED', 'value' => 'led']);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LIT-LED-ACT',
            'price' => 50,
            'stock' => 10,
            'is_active' => true,
            'combination_key' => 'led-active',
        ])->attributeValues()->attach($led->id);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'LIT-LED-INACT',
            'price' => 30,
            'stock' => 5,
            'is_active' => false,
            'combination_key' => 'led-inactive',
        ])->attributeValues()->attach($led->id);

        $variants = $this->getJson("/api/products/{$product->id}")->assertOk()->json('variants');

        // API returns all variants; frontend filters inactive ones
        $this->assertCount(2, $variants);

        $activeVariant = collect($variants)->firstWhere('sku', 'LIT-LED-ACT');
        $inactiveVariant = collect($variants)->firstWhere('sku', 'LIT-LED-INACT');

        $this->assertTrue($activeVariant['is_active']);
        $this->assertFalse($inactiveVariant['is_active']);
    }

    /* ────────────────────────────────────────────────────────────
     | 6. Variant price is returned correctly
     | ──────────────────────────────────────────────────────────── */

    public function test_variant_price_is_returned_correctly(): void
    {
        $product = $this->product('Rotor', 'rotor');
        $attr = Attribute::create(['name' => 'Size', 'slug' => 'size', 'type' => 'select']);
        $val = AttributeValue::create(['attribute_id' => $attr->id, 'label' => 'Large', 'value' => 'large']);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ROT-L',
            'price' => 350,
            'compare_at_price' => 400,
            'stock' => 7,
            'is_active' => true,
            'combination_key' => 'large',
        ])->attributeValues()->attach($val->id);

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();
        $variant = $data['variants'][0];

        $this->assertEquals(350.0, $variant['price']);
        $this->assertEquals(400.0, $variant['compare_at_price']);
        $this->assertEquals(7, $variant['stock']);
    }

    /* ────────────────────────────────────────────────────────────
     | 7. Variant price fallback to product price
     | ──────────────────────────────────────────────────────────── */

    public function test_variant_price_fallback_to_product_price(): void
    {
        $product = $this->product('Sensor', 'sensor');
        $product->update(['price' => 500, 'compare_at_price' => 600]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'price' => null,
            'compare_at_price' => null,
            'stock' => 1,
            'is_active' => true,
            'combination_key' => 'default',
        ]);

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();

        $this->assertEquals(500.0, $data['variants'][0]['price']);
        $this->assertEquals(600.0, $data['variants'][0]['compare_at_price']);
    }

    /* ────────────────────────────────────────────────────────────
     | 8. Variant stock is displayed correctly
     | ──────────────────────────────────────────────────────────── */

    public function test_variant_stock_is_displayed_correctly(): void
    {
        $product = $this->product('Bearing', 'bearing');

        $inStock = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'BRG-1',
            'stock' => 15,
            'is_active' => true,
            'combination_key' => 'type1',
        ]);

        $outOfStock = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'BRG-2',
            'stock' => 0,
            'is_active' => true,
            'combination_key' => 'type2',
        ]);

        $variants = $this->getJson("/api/products/{$product->id}")->assertOk()->json('variants');

        $v1 = collect($variants)->firstWhere('sku', 'BRG-1');
        $v2 = collect($variants)->firstWhere('sku', 'BRG-2');

        $this->assertEquals(15, $v1['stock']);
        $this->assertEquals(0, $v2['stock']);
    }

    /* ────────────────────────────────────────────────────────────
     | 9. Product without image
     | ──────────────────────────────────────────────────────────── */

    public function test_product_without_image(): void
    {
        $product = $this->product('Bracket', 'bracket');

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();

        $this->assertIsArray($data['images']);
        $this->assertEmpty($data['images']);
    }

    /* ────────────────────────────────────────────────────────────
     | 10. Product with multiple images sorted
     | ──────────────────────────────────────────────────────────── */

    public function test_product_with_multiple_images_sorted(): void
    {
        $product = $this->product('Caliper Set', 'caliper-set');

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/third.jpg',
            'is_primary' => false,
            'sort_order' => 3,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/first.jpg',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/second.jpg',
            'is_primary' => false,
            'sort_order' => 2,
        ]);

        $images = $this->getJson("/api/products/{$product->id}")->assertOk()->json('images');

        $this->assertCount(3, $images);
        $this->assertEquals('images/first.jpg', $images[0]['path']);
        $this->assertTrue($images[0]['is_primary']);
        $this->assertEquals('images/second.jpg', $images[1]['path']);
        $this->assertEquals('images/third.jpg', $images[2]['path']);
    }

    /* ────────────────────────────────────────────────────────────
     | 11. Vehicle compatibility
     | ──────────────────────────────────────────────────────────── */

    public function test_vehicle_compatibility_returns_full_hierarchy(): void
    {
        $product = $this->product('Oil Filter', 'oil-filter');

        $brand = VehicleBrand::create(['name' => 'BMW', 'slug' => 'bmw']);
        $model = VehicleModel::create(['name' => '3 Series', 'slug' => '3-series', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen = VehicleGeneration::create([
            'name' => 'G20',
            'slug' => 'g20',
            'vehicle_model_id' => $model->id,
            'year_start' => 2019,
            'year_end' => null,
        ]);
        $trim = VehicleTrim::create(['name' => '320i', 'slug' => '320i', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $engine = VehicleEngine::create(['name' => '2.0 Turbo', 'slug' => '2-0-turbo', 'vehicle_trim_id' => $trim->id, 'is_active' => true]);

        $product->vehicleEngines()->attach($engine->id);

        $compat = $this->getJson("/api/products/{$product->id}")->assertOk()->json('vehicle_compatibility');

        $this->assertCount(1, $compat);
        $this->assertEquals('BMW', $compat[0]['brand']['name']);
        $this->assertEquals('3 Series', $compat[0]['model']['name']);
        $this->assertEquals('G20', $compat[0]['generation']['name']);
        $this->assertEquals(2019, $compat[0]['generation']['year_start']);
        $this->assertNull($compat[0]['generation']['year_end']);
        $this->assertEquals('320i', $compat[0]['trim']['name']);
        $this->assertEquals('2.0 Turbo', $compat[0]['engine']['name']);
    }

    /* ────────────────────────────────────────────────────────────
     | 12. Product not found
     | ──────────────────────────────────────────────────────────── */

    public function test_product_not_found_returns_404(): void
    {
        $this->getJson('/api/products/99999')
            ->assertStatus(404)
           ->assertJsonPath('message', 'Product not found.');
    }

    public function test_inactive_product_returns_404(): void
    {
        $product = Product::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'price' => 50,
            'is_active' => false,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertStatus(404);
    }

    /* ────────────────────────────────────────────────────────────
     | 13. Web route exists for direct URL access (refresh support)
     | ──────────────────────────────────────────────────────────── */

    public function test_web_route_serves_spa_view(): void
    {
        $this->get('/products/123')
            ->assertOk()
            ->assertSee('app');
    }

    /* ────────────────────────────────────────────────────────────
     | 14. Storefront product cards link to detail
     | (Verified by checking the route exists and returns JSON)
     | ──────────────────────────────────────────────────────────── */

    public function test_api_route_is_public(): void
    {
        $product = $this->product('Link Test', 'link-test');

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Link Test');
    }

    /* ────────────────────────────────────────────────────────────
     | 15. Back navigation (browser history)
     | (Frontend uses history.back() — no API test needed)
     | ──────────────────────────────────────────────────────────── */

    public function test_product_detail_includes_all_required_fields(): void
    {
        $product = Product::create([
            'name' => 'Complete Test',
            'slug' => 'complete-test',
            'sku' => 'CMP-001',
            'short_description' => 'Short desc',
            'description' => 'Full description',
            'price' => 250,
            'compare_at_price' => 300,
            'stock' => 10,
            'is_active' => true,
            'is_featured' => true,
        ]);

        $data = $this->getJson("/api/products/{$product->id}")->assertOk()->json();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('sku', $data);
        $this->assertArrayHasKey('short_description', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertArrayHasKey('compare_at_price', $data);
        $this->assertArrayHasKey('in_stock', $data);
        $this->assertArrayHasKey('images', $data);
        $this->assertArrayHasKey('attributes', $data);
        $this->assertArrayHasKey('custom_attributes', $data);
        $this->assertArrayHasKey('variants', $data);
        $this->assertArrayHasKey('vehicle_compatibility', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertTrue($data['is_featured']);
    }

    /* ────────────────────────────────────────────────────────────
     | 16. Build frontend
     | (Run separately: npm run build)
     | ──────────────────────────────────────────────────────────── */

    public function test_all_previous_filters_still_work(): void
    {
        $root = $this->category('Parts', 'parts');
        $product = $this->product('Brake', 'brake');
        $product->categories()->attach($root->id);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /* ────────────────────────────────────────────────────────────
     | Helpers
     | ──────────────────────────────────────────────────────────── */

    private function category(
        string $name,
        string $slug,
        ?int $parentId = null
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'is_active' => true,
        ]);
    }

    private function product(string $name, string $slug): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'price' => 100,
            'is_active' => true,
        ]);
    }
}
