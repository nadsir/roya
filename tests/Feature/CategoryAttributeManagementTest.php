<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryAttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_replace_a_categorys_direct_attribute_configuration(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
        ]);
        $brand = $this->attribute('Brand', 'brand', 'select');
        $color = $this->attribute('Color', 'color', 'color');

        $this->putJson("/api/admin/categories/{$category->id}/attributes", [
            'attributes' => [
                [
                    'attribute_id' => $brand->id,
                    'is_enabled' => true,
                    'is_required' => true,
                    'is_filterable' => true,
                    'is_variant_axis' => false,
                    'sort_order' => 2,
                ],
                [
                    'attribute_id' => $color->id,
                    'is_enabled' => true,
                    'is_required' => false,
                    'is_filterable' => true,
                    'is_variant_axis' => true,
                    'sort_order' => 1,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $color->id)
            ->assertJsonPath('data.0.pivot.is_variant_axis', true)
            ->assertJsonPath('data.1.id', $brand->id)
            ->assertJsonPath('data.1.pivot.is_required', true);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_enabled' => true,
            'is_variant_axis' => true,
            'sort_order' => 1,
        ]);

        $this->getJson("/api/admin/categories/{$category->id}/attributes")
            ->assertOk()
            ->assertJsonPath('data.0.id', $color->id)
            ->assertJsonPath('data.0.state', 'enabled')
            ->assertJsonPath('data.0.config.is_variant_axis', true);
    }

    public function test_admin_can_disable_a_category_attribute(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
        ]);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $this->putJson("/api/admin/categories/{$category->id}/attributes", [
            'attributes' => [
                [
                    'attribute_id' => $brand->id,
                    'is_enabled' => false,
                    'is_required' => false,
                    'is_filterable' => false,
                    'is_variant_axis' => false,
                    'sort_order' => 0,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.pivot.is_enabled', 0);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $brand->id,
            'is_enabled' => false,
        ]);

        $this->getJson("/api/admin/categories/{$category->id}/attributes")
            ->assertOk()
            ->assertJsonPath('data.0.id', $brand->id)
            ->assertJsonPath('data.0.state', 'disabled')
            ->assertJsonPath('data.0.config.is_enabled', false);
    }

    public function test_get_returns_inherit_state_for_parent_attributes(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $parent = Category::create(['name' => 'Cars', 'slug' => 'cars']);
        $child = Category::create(['name' => 'Sedans', 'slug' => 'sedans', 'parent_id' => $parent->id]);

        $brand = $this->attribute('Brand', 'brand', 'select');
        $color = $this->attribute('Color', 'color', 'color');

        $parent->attributes()->attach($brand->id, [
            'is_enabled' => true, 'is_required' => true,
            'is_filterable' => true, 'is_variant_axis' => true, 'sort_order' => 0,
        ]);

        $parent->attributes()->attach($color->id, [
            'is_enabled' => false, 'is_required' => false,
            'is_filterable' => false, 'is_variant_axis' => false, 'sort_order' => 1,
        ]);

        $this->getJson("/api/admin/categories/{$child->id}/attributes")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brand->id)
            ->assertJsonPath('data.0.state', 'inherit')
            ->assertJsonPath('data.0.config.is_required', true);
    }

    public function test_number_attribute_cannot_be_a_variant_axis(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
        ]);
        $weight = $this->attribute('Weight', 'weight', 'number');

        $this->putJson("/api/admin/categories/{$category->id}/attributes", [
            'attributes' => [[
                'attribute_id' => $weight->id,
                'is_enabled' => true,
                'is_required' => false,
                'is_filterable' => true,
                'is_variant_axis' => true,
                'sort_order' => 0,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attributes');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function attribute(string $name, string $slug, string $type): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }
}
