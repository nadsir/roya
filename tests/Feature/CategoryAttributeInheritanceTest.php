<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Services\EffectiveCategoryAttributesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAttributeInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private EffectiveCategoryAttributesResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(EffectiveCategoryAttributesResolver::class);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 1: Child inherits parent when no override exists
    |--------------------------------------------------------------------------
    */
    public function test_child_inherits_parent_attribute_when_no_override_exists(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $inherited = $this->resolver->for($child)->firstWhere('id', $brand->id);

        $this->assertNotNull($inherited);
        $this->assertTrue($inherited->pivot->is_filterable);
        $this->assertTrue($inherited->pivot->is_required);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 2: Child explicitly disables inherited attribute
    |--------------------------------------------------------------------------
    */
    public function test_child_explicitly_disables_inherited_attribute(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $result = $this->resolver->for($child)->firstWhere('id', $brand->id);
        $this->assertNull($result);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 3: Child overrides only is_filterable, inherits rest
    |--------------------------------------------------------------------------
    */
    public function test_child_overrides_is_filterable_only_while_inheriting_rest(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => false,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $result = $this->resolver->for($child)->firstWhere('id', $brand->id);

        $this->assertNotNull($result);
        $this->assertFalse($result->pivot->is_filterable);
        $this->assertTrue($result->pivot->is_required);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 4: Grandchild inherits disabled state from child
    |--------------------------------------------------------------------------
    */
    public function test_grandchild_inherits_disabled_state_from_child(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Engine', 'engine', $root->id);
        $grandchild = $this->category('Fuel system', 'fuel-system', $child->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $result = $this->resolver->for($grandchild)->firstWhere('id', $brand->id);
        $this->assertNull($result);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 5: Child re-enables attribute that parent disabled
    |--------------------------------------------------------------------------
    */
    public function test_child_reenables_attribute_that_parent_disabled(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $result = $this->resolver->for($child)->firstWhere('id', $brand->id);

        $this->assertNotNull($result);
        $this->assertTrue((bool) $result->pivot->is_enabled);
        $this->assertTrue($result->pivot->is_filterable);
        $this->assertTrue($result->pivot->is_required);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 6: No active attributes anywhere returns empty
    |--------------------------------------------------------------------------
    */
    public function test_no_active_attributes_anywhere_returns_empty(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $result = $this->resolver->for($child);
        $this->assertTrue($result->isEmpty());
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 7: Existing product attribute must not appear when not effective
    |--------------------------------------------------------------------------
    */
    public function test_disabled_attribute_must_not_appear_in_effective(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $result = $this->resolver->for($child)->firstWhere('id', $brand->id);
        $this->assertNull($result);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 8: Changing parent config updates descendants without overrides
    |--------------------------------------------------------------------------
    */
    public function test_changing_parent_config_updates_descendants_without_overrides(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Engine', 'engine', $root->id);
        $grandchild = $this->category('Fuel system', 'fuel-system', $child->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $before = $this->resolver->for($grandchild)->firstWhere('id', $brand->id);
        $this->assertNotNull($before);
        $this->assertFalse($before->pivot->is_required);

        $root->attributes()->updateExistingPivot($brand->id, [
            'is_required' => true,
        ]);

        $after = $this->resolver->for($grandchild)->firstWhere('id', $brand->id);
        $this->assertNotNull($after);
        $this->assertTrue($after->pivot->is_required);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 9: Changing child override must not modify parent
    |--------------------------------------------------------------------------
    */
    public function test_changing_child_override_must_not_modify_parent(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Brakes', 'brakes', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => true,
            'sort_order' => 2,
        ]);

        $parentResult = $this->resolver->for($root)->firstWhere('id', $brand->id);
        $childResult = $this->resolver->for($child)->firstWhere('id', $brand->id);

        $this->assertTrue($parentResult->pivot->is_filterable);
        $this->assertTrue($parentResult->pivot->is_required);
        $this->assertFalse($parentResult->pivot->is_variant_axis);

        $this->assertFalse($childResult->pivot->is_filterable);
        $this->assertFalse($childResult->pivot->is_required);
        $this->assertTrue($childResult->pivot->is_variant_axis);
    }

    /*
    |--------------------------------------------------------------------------
    | Inheritance + Override across four levels
    |--------------------------------------------------------------------------
    */
    public function test_resolver_inherits_and_overrides_configuration_across_four_levels(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Engine', 'engine', $root->id);
        $grandchild = $this->category('Fuel system', 'fuel-system', $child->id);
        $greatGrandchild = $this->category('Injection', 'injection', $grandchild->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 4,
        ]);

        $inherited = $this->resolver->for($greatGrandchild)->firstWhere('id', $brand->id);

        $this->assertNotNull($inherited);
        $this->assertTrue($inherited->pivot->is_filterable);
        $this->assertTrue($inherited->pivot->is_required);
        $this->assertFalse($inherited->pivot->is_variant_axis);
        $this->assertSame(4, $inherited->pivot->sort_order);

        $grandchild->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => true,
            'sort_order' => 1,
        ]);

        $overridden = $this->resolver->for($greatGrandchild)->firstWhere('id', $brand->id);

        $this->assertNotNull($overridden);
        $this->assertFalse($overridden->pivot->is_filterable);
        $this->assertFalse($overridden->pivot->is_required);
        $this->assertTrue($overridden->pivot->is_variant_axis);
        $this->assertSame(1, $overridden->pivot->sort_order);
    }

    /*
    |--------------------------------------------------------------------------
    | Re-enable at deeper level restores attribute
    |--------------------------------------------------------------------------
    */
    public function test_reenable_at_deeper_level_restores_attribute(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Engine', 'engine', $root->id);
        $grandchild = $this->category('Fuel system', 'fuel-system', $child->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $grandchild->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
            'sort_order' => 3,
        ]);

        $result = $this->resolver->for($grandchild)->firstWhere('id', $brand->id);

        $this->assertNotNull($result);
        $this->assertTrue((bool) $result->pivot->is_enabled);
        $this->assertTrue($result->pivot->is_filterable);
        $this->assertFalse($result->pivot->is_required);
        $this->assertTrue($result->pivot->is_variant_axis);
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 12: forMany() correctly excludes disabled attributes
    |--------------------------------------------------------------------------
    */
    public function test_formany_excludes_disabled_attributes(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Misc', 'misc', $root->id);
        $brand = $this->attribute('Brand', 'brand', 'select');
        $warranty = $this->attribute('Warranty', 'warranty-months', 'number');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $root->attributes()->attach($warranty->id, [
            'is_enabled' => true,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $child->attributes()->attach($warranty->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $categories = Category::query()
            ->whereIn('id', [$root->id, $child->id])
            ->with('attributes.values')
            ->get();

        $result = $this->resolver->forMany($categories);

        $rootAttributes = $result->get($root->id);
        $childAttributes = $result->get($child->id);

        $this->assertNotNull($rootAttributes->firstWhere('id', $brand->id));
        $this->assertNotNull($rootAttributes->firstWhere('id', $warranty->id));

        $this->assertNotNull($childAttributes->firstWhere('id', $brand->id));
        $this->assertNull($childAttributes->firstWhere('id', $warranty->id));
    }

    /*
    |--------------------------------------------------------------------------
    | TEST 13: forMany() grandchild inherits disabled state from child
    |--------------------------------------------------------------------------
    */
    public function test_formany_grandchild_inherits_disabled_state(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $child = $this->category('Misc', 'misc', $root->id);
        $grandchild = $this->category('Sub', 'sub', $child->id);
        $brand = $this->attribute('Brand', 'brand', 'select');

        $root->attributes()->attach($brand->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $child->attributes()->attach($brand->id, [
            'is_enabled' => false,
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);

        $categories = Category::query()
            ->whereIn('id', [$root->id, $child->id, $grandchild->id])
            ->with('attributes.values')
            ->get();

        $result = $this->resolver->forMany($categories);

        $grandchildAttributes = $result->get($grandchild->id);
        $this->assertNull($grandchildAttributes->firstWhere('id', $brand->id));
    }

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

    private function attribute(string $name, string $slug, string $type): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }
}
