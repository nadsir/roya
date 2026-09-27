<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_selected_from_category_attribute_settings(): void
    {
        $category = Category::create([
            'name' => 'Brake pads',
            'slug' => 'brake-pads',
            'is_active' => true,
        ]);

        $included = Attribute::create([
            'name' => 'Brand',
            'slug' => 'brand',
            'type' => 'select',
            'is_filterable' => false,
            'is_required' => false,
        ]);

        $excluded = Attribute::create([
            'name' => 'Material',
            'slug' => 'material',
            'type' => 'select',
            'is_filterable' => true,
            'is_required' => true,
        ]);

        $brembo = AttributeValue::create([
            'attribute_id' => $included->id,
            'label' => 'Brembo',
            'value' => 'brembo',
            'sort_order' => 1,
        ]);

        $category->attributes()->attach($included->id, [
            'is_filterable' => true,
            'is_required' => true,
            'is_variant_axis' => true,
            'sort_order' => 2,
        ]);

        $category->attributes()->attach($excluded->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'name' => 'Brembo brake pads',
            'slug' => 'brembo-brake-pads',
            'sku' => 'QA-BRE-1',
            'price' => 120,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $product->categories()->attach($category->id);
        $product->attributeValues()->attach(
            $brembo->id,
            ['attribute_id' => $included->id]
        );

        $this->getJson('/api/categories/brake-pads/filters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brand')
            ->assertJsonPath('data.0.is_filterable', true)
            ->assertJsonPath('data.0.is_required', true)
            ->assertJsonPath('data.0.is_variant_axis', true)
            ->assertJsonPath('data.0.sort_order', 2)
            ->assertJsonCount(1, 'data.0.values')
            ->assertJsonPath('data.0.values.0.label', 'Brembo')
            ->assertJsonPath('data.0.values.0.count', 1);
    }
}
