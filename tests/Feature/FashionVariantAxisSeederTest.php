<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FashionVariantAxisSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FashionVariantAxisSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_enables_variant_axes_without_touching_other_settings(): void
    {
        $category = Category::create([
            'name' => 'Shirts',
            'slug' => 'shirts',
            'is_active' => true,
        ]);

        $color = $this->attribute('Color', 'color', 'color');
        $size = $this->attribute('Size', 'size', 'select');
        $material = $this->attribute('Material', 'material', 'select');
        $this->value($color, 'Black', 'black');
        $this->value($size, 'Large', 'large');

        $category->attributes()->attach($color->id, [
            'is_enabled' => true,
            'is_required' => false,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 3,
        ]);
        $category->attributes()->attach($size->id, [
            'is_enabled' => true,
            'is_required' => true,
            'is_filterable' => false,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);
        $category->attributes()->attach($material->id, [
            'is_enabled' => true,
            'is_required' => false,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 2,
        ]);

        $this->seed(FashionVariantAxisSeeder::class);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_variant_axis' => true,
            'is_filterable' => true,
            'sort_order' => 3,
        ]);
        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $size->id,
            'is_variant_axis' => true,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        // Material is not a variant axis and must be left untouched.
        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $material->id,
            'is_variant_axis' => false,
        ]);
    }

    public function test_it_is_idempotent(): void
    {
        $category = Category::create(['name' => 'Handbags', 'slug' => 'handbags']);
        $color = $this->attribute('Color', 'color', 'color');
        $this->value($color, 'Black', 'black');

        $category->attributes()->attach($color->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_variant_axis' => false,
        ]);

        $this->seed(FashionVariantAxisSeeder::class);
        $first = DB::table('category_attributes')->orderBy('id')->get();

        $this->seed(FashionVariantAxisSeeder::class);
        $second = DB::table('category_attributes')->orderBy('id')->get();

        $this->assertSame($first->count(), $second->count());
        $this->assertEquals(
            $first->map(fn ($row) => (array) $row)->all(),
            $second->map(fn ($row) => (array) $row)->all()
        );
        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_variant_axis' => true,
        ]);
    }

    public function test_it_enables_color_and_size_for_clothing_and_mantos(): void
    {
        $clothing = Category::create(['name' => 'Clothing', 'slug' => 'clothing']);
        $mantos = Category::create(['name' => 'Mantos', 'slug' => 'mantos']);
        $color = $this->attribute('Color', 'color', 'color');
        $size = $this->attribute('Size', 'size', 'select');
        $this->value($color, 'Black', 'black');
        $this->value($size, 'Large', 'large');

        foreach ([$clothing, $mantos] as $category) {
            $category->attributes()->attach($color->id, [
                'is_enabled' => true,
                'is_filterable' => true,
                'is_variant_axis' => false,
            ]);
            $category->attributes()->attach($size->id, [
                'is_enabled' => true,
                'is_filterable' => true,
                'is_variant_axis' => false,
            ]);
        }

        $this->seed(FashionVariantAxisSeeder::class);

        foreach ([$clothing, $mantos] as $category) {
            $this->assertDatabaseHas('category_attributes', [
                'category_id' => $category->id,
                'attribute_id' => $color->id,
                'is_variant_axis' => true,
            ]);
            $this->assertDatabaseHas('category_attributes', [
                'category_id' => $category->id,
                'attribute_id' => $size->id,
                'is_variant_axis' => true,
            ]);
        }
    }

    public function test_database_seeder_skips_automotive_seeders_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Category::count());
        $this->assertSame(0, Attribute::count());
        $this->assertDatabaseMissing('categories', ['slug' => 'car-parts']);
        $this->assertDatabaseMissing('attributes', ['slug' => 'brand']);
    }

    public function test_it_skips_missing_categories_and_disabled_rows(): void
    {
        // Only "clothing" exists; the other mapped categories and attributes are
        // absent, so the seeder must skip them without throwing.
        $category = Category::create(['name' => 'Clothing', 'slug' => 'clothing']);
        $color = $this->attribute('Color', 'color', 'color');
        $this->value($color, 'Black', 'black');

        $category->attributes()->attach($color->id, [
            'is_enabled' => false,
            'is_filterable' => true,
            'is_variant_axis' => false,
        ]);

        $this->seed(FashionVariantAxisSeeder::class);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_enabled' => false,
            'is_variant_axis' => false,
        ]);
        // Nothing was inserted for the absent categories/attributes.
        $this->assertSame(1, DB::table('category_attributes')->count());
    }

    public function test_it_skips_attributes_without_values(): void
    {
        $category = Category::create(['name' => 'Shirts', 'slug' => 'shirts']);
        $color = $this->attribute('Color', 'color', 'color');
        $size = $this->attribute('Size', 'size', 'select');

        $category->attributes()->attach($color->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);
        $category->attributes()->attach($size->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $this->seed(FashionVariantAxisSeeder::class);

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $color->id,
            'is_variant_axis' => false,
        ]);
        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'attribute_id' => $size->id,
            'is_variant_axis' => false,
        ]);
    }

    public function test_it_skips_unsupported_attribute_types(): void
    {
        Category::create(['name' => 'Shirts', 'slug' => 'shirts']);
        $size = $this->attribute('Size', 'size', 'number');
        $this->value($size, '42', '42');

        $this->seed(FashionVariantAxisSeeder::class);

        $this->assertDatabaseMissing('category_attributes', [
            'attribute_id' => $size->id,
            'is_variant_axis' => true,
        ]);
    }

    public function test_seeded_axis_is_returned_in_admin_meta_and_allows_variants(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);

        $category = Category::create([
            'name' => 'Shirts',
            'slug' => 'shirts',
            'is_active' => true,
        ]);
        $color = $this->attribute('Color', 'color', 'color');
        $size = $this->attribute('Size', 'size', 'select');
        $black = $this->value($color, 'Black', 'black');
        $large = $this->value($size, 'Large', 'large');

        $category->attributes()->attach($color->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 0,
        ]);
        $category->attributes()->attach($size->id, [
            'is_enabled' => true,
            'is_filterable' => true,
            'is_variant_axis' => false,
            'sort_order' => 1,
        ]);

        $this->seed(FashionVariantAxisSeeder::class);

        $this->getJson('/api/admin/products/meta')
            ->assertOk()
            ->assertJsonPath("category_attributes.{$category->id}.0.slug", 'color')
            ->assertJsonPath("category_attributes.{$category->id}.0.pivot.is_variant_axis", true)
            ->assertJsonPath("category_attributes.{$category->id}.1.slug", 'size')
            ->assertJsonPath("category_attributes.{$category->id}.1.pivot.is_variant_axis", true);

        $this->postJson('/api/admin/products', [
            'name' => 'Black shirt',
            'slug' => 'black-shirt',
            'price' => 100,
            'category_ids' => [$category->id],
            'variants' => [[
                'stock' => 4,
                'attribute_value_ids' => [$black->id, $large->id],
            ]],
        ])->assertCreated();
    }

    private function attribute(string $name, string $slug, string $type): Attribute
    {
        return Attribute::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
        ]);
    }

    private function value(Attribute $attribute, string $label, string $value): AttributeValue
    {
        return AttributeValue::create([
            'attribute_id' => $attribute->id,
            'label' => $label,
            'value' => $value,
        ]);
    }
}
