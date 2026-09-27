<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCustomAttributeValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_scalar_attribute_values_are_exposed_and_filterable(): void
    {
        $category = Category::create([
            'name' => 'Engine',
            'slug' => 'engine',
            'is_active' => true,
        ]);

        $weight = $this->attribute('Weight', 'weight', 'number');
        $genuine = $this->attribute('Genuine', 'genuine', 'boolean');
        $note = $this->attribute('Note', 'note', 'text');

        foreach ([$weight, $genuine, $note] as $attribute) {
            $category->attributes()->attach($attribute->id, [
                'is_filterable' => true,
                'is_required' => false,
                'is_variant_axis' => false,
            ]);
        }

        $product = Product::create([
            'name' => 'Engine mount',
            'slug' => 'engine-mount',
            'price' => 100,
            'is_active' => true,
        ]);
        $product->categories()->attach($category);

        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $weight->id,
            'value_type' => 'number',
            'value_number' => 12.5,
        ]);
        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $genuine->id,
            'value_type' => 'boolean',
            'value_boolean' => true,
        ]);
        ProductCustomAttributeValue::create([
            'product_id' => $product->id,
            'attribute_id' => $note->id,
            'value_type' => 'text',
            'value_text' => 'Suitable for TU5',
        ]);

        $this->getJson('/api/products?category=engine&weight=12.5')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount')
            ->assertJsonPath('data.0.custom_attributes.0.value', 12.5);

        $this->getJson('/api/products?category=engine&genuine=true')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount');

        $this->getJson('/api/products?category=engine&note=Suitable%20for%20TU5')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'engine-mount');
    }

    public function test_admin_product_api_rejects_custom_values_for_select_attributes(): void
    {
        $this->authenticate();
        $select = $this->attribute('Brand', 'brand', 'select');

        $this->postJson('/api/admin/products', [
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
            'custom_attribute_values' => [[
                'attribute_id' => $select->id,
                'value' => 'Brembo',
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_attribute_values.0.value');
    }

    public function test_admin_product_api_stores_a_number_custom_value(): void
    {
        $this->authenticate();
        $category = Category::create([
            'name' => 'Cooling system',
            'slug' => 'cooling-system',
            'is_active' => true,
        ]);
        $weight = $this->attribute('Weight', 'weight', 'number');
        $category->attributes()->attach($weight->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Water pump',
            'slug' => 'water-pump',
            'price' => 100,
            'category_ids' => [$category->id],
            'custom_attribute_values' => [[
                'attribute_id' => $weight->id,
                'value' => 1.75,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('product_custom_attribute_values', [
            'attribute_id' => $weight->id,
            'value_type' => 'number',
            'value_number' => 1.75,
        ]);
    }

    public function test_admin_product_api_rejects_custom_values_not_effective_for_the_category(): void
    {
        $this->authenticate();
        $category = Category::create([
            'name' => 'Brake system',
            'slug' => 'brake-system',
            'is_active' => true,
        ]);
        $weight = $this->attribute('Weight', 'weight', 'number');

        $this->postJson('/api/admin/products', [
            'name' => 'Brake disc',
            'slug' => 'brake-disc',
            'price' => 100,
            'category_ids' => [$category->id],
            'custom_attribute_values' => [[
                'attribute_id' => $weight->id,
                'value' => 7.2,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_attribute_values.0.value');
    }

    public function test_admin_product_api_validates_boolean_and_text_custom_values(): void
    {
        $this->authenticate();
        $category = Category::create([
            'name' => 'Engine',
            'slug' => 'engine',
            'is_active' => true,
        ]);
        $genuine = $this->attribute('Genuine', 'genuine', 'boolean');
        $note = $this->attribute('Note', 'note', 'text');

        foreach ([$genuine, $note] as $attribute) {
            $category->attributes()->attach($attribute->id, [
                'is_filterable' => false,
                'is_required' => false,
                'is_variant_axis' => false,
            ]);
        }

        $this->postJson('/api/admin/products', [
            'name' => 'Engine mount',
            'slug' => 'engine-mount',
            'price' => 100,
            'category_ids' => [$category->id],
            'custom_attribute_values' => [
                ['attribute_id' => $genuine->id, 'value' => 'false'],
                ['attribute_id' => $note->id, 'value' => 'Suitable for TU5'],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('product_custom_attribute_values', [
            'attribute_id' => $genuine->id,
            'value_type' => 'boolean',
            'value_boolean' => false,
        ]);
        $this->assertDatabaseHas('product_custom_attribute_values', [
            'attribute_id' => $note->id,
            'value_type' => 'text',
            'value_text' => 'Suitable for TU5',
        ]);
    }

    public function test_custom_values_must_be_shared_by_all_selected_categories(): void
    {
        $this->authenticate();
        $engine = Category::create(['name' => 'Engine', 'slug' => 'engine', 'is_active' => true]);
        $brakes = Category::create(['name' => 'Brakes', 'slug' => 'brakes', 'is_active' => true]);
        $weight = $this->attribute('Weight', 'weight', 'number');

        $engine->attributes()->attach($weight->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $this->postJson('/api/admin/products', [
            'name' => 'Incompatible part',
            'slug' => 'incompatible-part',
            'price' => 100,
            'category_ids' => [$engine->id, $brakes->id],
            'custom_attribute_values' => [[
                'attribute_id' => $weight->id,
                'value' => 1.5,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('custom_attribute_values.0.value');
    }

    private function attribute(string $name, string $slug, string $type): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }
}
