<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_an_option_attribute_and_its_values(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $attribute = $this->postJson('/api/admin/attributes', [
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
            'sort_order' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'brand')
            ->json('data');

        $value = $this->postJson("/api/admin/attributes/{$attribute['id']}/values", [
            'label' => 'Brembo',
            'value' => 'brembo',
            'sort_order' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.value', 'brembo')
            ->json('data');

        $this->putJson("/api/admin/attributes/{$attribute['id']}/values/{$value['id']}", [
            'label' => 'Brembo Italy',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Brembo Italy');

        $this->getJson('/api/admin/attributes')
            ->assertOk()
            ->assertJsonPath('data.0.values.0.value', 'brembo');

        $this->deleteJson("/api/admin/attributes/{$attribute['id']}/values/{$value['id']}")
            ->assertNoContent();

        $this->deleteJson("/api/admin/attributes/{$attribute['id']}")
            ->assertNoContent();
    }

    public function test_scalar_attribute_cannot_have_predefined_values(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $attribute = Attribute::create([
            'name' => 'Weight',
            'slug' => 'weight',
            'type' => 'number',
        ]);

        $this->postJson("/api/admin/attributes/{$attribute->id}/values", [
            'label' => 'One',
            'value' => '1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute');
    }

    public function test_configured_attribute_cannot_change_type_or_be_deleted(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $attribute = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $category = Category::create([
            'name' => 'Brakes',
            'slug' => 'brakes',
        ]);
        $category->attributes()->attach($attribute);

        $this->putJson("/api/admin/attributes/{$attribute->id}", [
            'type' => 'text',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->deleteJson("/api/admin/attributes/{$attribute->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attribute');
    }

    public function test_attribute_type_cannot_change_after_values_are_defined(): void
    {
        Sanctum::actingAs($this->admin(), ['admin']);

        $attribute = Attribute::create([
            'name' => 'Color',
            'slug' => 'color',
            'type' => 'color',
        ]);
        AttributeValue::create([
            'attribute_id' => $attribute->id,
            'label' => 'Black',
            'value' => 'black',
        ]);

        $this->putJson("/api/admin/attributes/{$attribute->id}", [
            'type' => 'text',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
