<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_returns_all_category_depths_and_effective_attributes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $child = Category::create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
        $leaf = Category::create(['name' => 'Leaf', 'slug' => 'leaf', 'parent_id' => $child->id]);
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $root->attributes()->attach($brand, [
            'is_required' => false,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/admin/products/meta')
            ->assertOk()
            ->assertJsonPath('categories.0.children.0.children.0.slug', 'leaf')
            ->assertJsonPath("category_attributes.{$leaf->id}.0.slug", 'brand');
    }

    public function test_meta_excludes_disabled_attribute_from_child_category(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);

        $root = Category::create(['name' => 'Root', 'slug' => 'root']);
        $child = Category::create(['name' => 'Misc', 'slug' => 'misc', 'parent_id' => $root->id]);
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $warranty = Attribute::create(['name' => 'Warranty', 'slug' => 'warranty-months', 'type' => 'number']);

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true, 'is_required' => false,
            'is_filterable' => true, 'is_variant_axis' => false, 'sort_order' => 0,
        ]);

        $root->attributes()->attach($warranty->id, [
            'is_enabled' => true, 'is_required' => false,
            'is_filterable' => false, 'is_variant_axis' => false, 'sort_order' => 1,
        ]);

        $child->attributes()->attach($warranty->id, [
            'is_enabled' => false, 'is_required' => false,
            'is_filterable' => false, 'is_variant_axis' => false, 'sort_order' => 0,
        ]);

        $response = $this->getJson('/api/admin/products/meta')->assertOk();

        $rootAttrs = $response->json("category_attributes.{$root->id}");
        $this->assertNotNull($rootAttrs);
        $this->assertCount(2, $rootAttrs);
        $this->assertEquals('brand', $rootAttrs[0]['slug']);
        $this->assertEquals('warranty-months', $rootAttrs[1]['slug']);

        $childAttrs = $response->json("category_attributes.{$child->id}");
        $this->assertNotNull($childAttrs);
        $this->assertCount(1, $childAttrs);
        $this->assertEquals('brand', $childAttrs[0]['slug']);
    }
}
