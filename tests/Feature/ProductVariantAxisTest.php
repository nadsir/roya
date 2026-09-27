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

class ProductVariantAxisTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_endpoints_require_an_active_admin(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ])->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ])->assertForbidden();
    }

    public function test_variants_only_accept_values_from_category_variant_axes(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $color = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);
        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $black = AttributeValue::create([
            'attribute_id' => $color->id,
            'label' => 'Black',
            'value' => 'black',
            'hex_color' => '#000000',
        ]);
        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);

        $category->attributes()->attach($color->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);
        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 2,
                'attribute_value_ids' => [$brembo->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.attribute_value_ids');

        $this->postJson('/api/admin/products', [
            'name' => 'Black brake pad',
            'slug' => 'black-brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 2,
                'attribute_value_ids' => [$black->id],
            ]],
        ])->assertCreated();
    }

    public function test_product_values_must_belong_to_an_effective_category_attribute(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake system',
            'slug' => 'brake-system',
            'is_active' => true,
        ]);
        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $material = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);
        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $ceramic = AttributeValue::create([
            'attribute_id' => $material->id,
            'label' => 'Ceramic',
            'value' => 'ceramic',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'attribute_value_ids' => [$ceramic->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute_value_ids');

        $this->postJson('/api/admin/products', [
            'name' => 'Brembo brake pad',
            'slug' => 'brembo-brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'attribute_value_ids' => [$brembo->id],
        ])->assertCreated();
    }

    public function test_product_values_must_be_shared_by_all_selected_categories(): void
    {
        $this->authenticate();

        $brakes = Category::create([
            'name' => 'Brake system',
            'slug' => 'brake-system',
            'is_active' => true,
        ]);
        $electrical = Category::create([
            'name' => 'Electrical',
            'slug' => 'electrical',
            'is_active' => true,
        ]);
        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $material = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);
        $ceramic = AttributeValue::create([
            'attribute_id' => $material->id,
            'label' => 'Ceramic',
            'value' => 'ceramic',
        ]);

        foreach ([$brakes, $electrical] as $category) {
            $category->attributes()->attach($brand->id, [
                'is_filterable' => true,
                'is_required' => false,
                'is_variant_axis' => false,
            ]);
        }
        $brakes->attributes()->attach($material->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Incompatible part',
            'slug' => 'incompatible-part',
            'price' => 100,
            'category_ids' => [$brakes->id, $electrical->id],
            'attribute_value_ids' => [$ceramic->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute_value_ids');

        $this->postJson('/api/admin/products', [
            'name' => 'Compatible part',
            'slug' => 'compatible-part',
            'price' => 100,
            'category_ids' => [$brakes->id, $electrical->id],
            'attribute_value_ids' => [$bosch->id],
        ])->assertCreated();
    }

    public function test_variant_missing_required_axis_is_rejected(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $voltage = Attribute::create([
            'name' => 'Voltage',
            'slug' => 'voltage',
            'type' => 'select',
        ]);
        $position = Attribute::create([
            'name' => 'Position',
            'slug' => 'position',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $v12 = AttributeValue::create([
            'attribute_id' => $voltage->id,
            'label' => '12V',
            'value' => '12v',
        ]);
        $left = AttributeValue::create([
            'attribute_id' => $position->id,
            'label' => 'Left',
            'value' => 'left',
        ]);

        foreach ([$brand, $voltage, $position] as $attr) {
            $category->attributes()->attach($attr->id, [
                'is_filterable' => true,
                'is_required' => false,
                'is_variant_axis' => true,
            ]);
        }

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id, $v12->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.attribute_value_ids');
    }

    public function test_variant_with_duplicate_axis_value_is_rejected(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id, $bosch->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.attribute_value_ids');
    }

    public function test_variant_with_all_axes_passes_validation(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $position = Attribute::create([
            'name' => 'Position',
            'slug' => 'position',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $left = AttributeValue::create([
            'attribute_id' => $position->id,
            'label' => 'Left',
            'value' => 'left',
        ]);

        foreach ([$brand, $position] as $attr) {
            $category->attributes()->attach($attr->id, [
                'is_filterable' => true,
                'is_required' => false,
                'is_variant_axis' => true,
            ]);
        }

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id, $left->id],
            ]],
        ])->assertCreated();
    }

    public function test_variant_with_value_from_non_axis_attribute_is_rejected(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $material = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $ceramic = AttributeValue::create([
            'attribute_id' => $material->id,
            'label' => 'Ceramic',
            'value' => 'ceramic',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);
        $category->attributes()->attach($material->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 5,
                'attribute_value_ids' => [$brembo->id, $ceramic->id],
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variants.0.attribute_value_ids');
    }

    public function test_smart_update_preserves_existing_variant_ids(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Brake pad set',
            'slug' => 'brake-pad-set',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                [
                    'stock' => 5,
                    'attribute_value_ids' => [$brembo->id],
                ],
                [
                    'stock' => 3,
                    'attribute_value_ids' => [$bosch->id],
                ],
            ],
        ])->assertCreated();

        $productId = $response->json('id');
        $variantIds = collect($response->json('variants'))->pluck('id')->sort()->values()->all();
        $this->assertCount(2, $variantIds);

        $updateResponse = $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'Brake pad set',
            'slug' => 'brake-pad-set',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                [
                    'stock' => 10,
                    'attribute_value_ids' => [$brembo->id],
                ],
            ],
        ])->assertOk();

        $updatedVariantIds = collect($updateResponse->json('variants'))->pluck('id')->sort()->values()->all();
        $this->assertCount(1, $updatedVariantIds);
        $this->assertContains($variantIds[0], $updatedVariantIds);
    }

    public function test_smart_update_removes_old_and_creates_new_variants(): void
    {
        $this->authenticate();

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $brand = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Brembo',
            'value' => 'brembo',
        ]);
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);
        $trw = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'TRW',
            'value' => 'trw',
        ]);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Brake pad set',
            'slug' => 'brake-pad-set',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                ['stock' => 5, 'attribute_value_ids' => [$brembo->id]],
                ['stock' => 3, 'attribute_value_ids' => [$bosch->id]],
                ['stock' => 2, 'attribute_value_ids' => [$trw->id]],
            ],
        ])->assertCreated();

        $productId = $response->json('id');
        $originalIds = collect($response->json('variants'))->pluck('id')->sort()->values()->all();
        $this->assertCount(3, $originalIds);

        $updateResponse = $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'Brake pad set',
            'slug' => 'brake-pad-set',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [
                ['stock' => 5, 'attribute_value_ids' => [$brembo->id]],
                ['stock' => 7, 'attribute_value_ids' => [$trw->id]],
            ],
        ])->assertOk();

        $updatedVariants = collect($updateResponse->json('variants'));
        $this->assertCount(2, $updatedVariants);

        $updatedIds = $updatedVariants->pluck('id')->sort()->values()->all();
        $this->assertContains($originalIds[0], $updatedIds);
        $this->assertNotContains($originalIds[1], $updatedIds);
        $this->assertContains($originalIds[2], $updatedIds);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }
}
