<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tree_api_returns_all_active_levels_and_excludes_inactive_categories(): void
    {
        $root = $this->category('Car parts', 'car-parts', null, 2);
        $engine = $this->category('Engine', 'engine', $root->id, 1);
        $this->category('Fuel system', 'fuel-system', $engine->id, 1);
        $this->category('Inactive', 'inactive', null, 1, false);

        $this->getJson('/api/categories/tree')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'car-parts')
            ->assertJsonPath('data.0.children.0.slug', 'engine')
            ->assertJsonPath('data.0.children.0.children.0.slug', 'fuel-system')
            ->assertJsonMissing(['slug' => 'inactive']);
    }

    public function test_authenticated_admin_can_create_and_update_a_category(): void
    {
        $this->authenticate();

        $created = $this->postJson('/api/admin/categories', [
            'name' => 'Brakes',
            'slug' => 'brakes',
            'description' => 'Brake components',
            'is_active' => true,
            'sort_order' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'brakes');

        $categoryId = $created->json('data.id');

        $this->putJson("/api/admin/categories/{$categoryId}", [
            'name' => 'Brake system',
            'sort_order' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Brake system')
            ->assertJsonPath('data.sort_order', 1);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'slug' => 'brakes',
            'name' => 'Brake system',
        ]);
    }

    public function test_non_admin_users_cannot_manage_categories(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson('/api/admin/categories', [
            'name' => 'Brakes',
            'slug' => 'brakes',
        ])->assertForbidden();
    }

    public function test_admin_cannot_make_a_category_its_own_parent_or_a_descendant_parent(): void
    {
        $this->authenticate();

        $root = $this->category('Car parts', 'car-parts');
        $engine = $this->category('Engine', 'engine', $root->id);
        $fuel = $this->category('Fuel system', 'fuel-system', $engine->id);

        $this->putJson("/api/admin/categories/{$engine->id}", [
            'parent_id' => $engine->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');

        $this->putJson("/api/admin/categories/{$root->id}", [
            'parent_id' => $fuel->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_admin_cannot_delete_categories_with_dependencies(): void
    {
        $this->authenticate();

        $withChild = $this->category('Engine', 'engine');
        $this->category('Fuel system', 'fuel-system', $withChild->id);

        $withProduct = $this->category('Brakes', 'brakes');
        $product = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'price' => 100,
        ]);
        $withProduct->products()->attach($product);

        $withAttribute = $this->category('Electrical', 'electrical');
        $attribute = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
        ]);
        $withAttribute->attributes()->attach($attribute);

        $empty = $this->category('Cooling', 'cooling');

        foreach ([$withChild, $withProduct, $withAttribute] as $category) {
            $this->deleteJson("/api/admin/categories/{$category->id}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('category');
        }

        $this->deleteJson("/api/admin/categories/{$empty->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $empty->id]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }

    private function category(
        string $name,
        string $slug,
        ?int $parentId = null,
        int $sortOrder = 0,
        bool $isActive = true
    ): Category {
        return Category::create([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);
    }
}
