<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use App\Models\ProductVariant;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    /* ────────────────────────────────────────────────────────────
     | Existing tests preserved
     | ──────────────────────────────────────────────────────────── */

    public function test_product_filters_only_accept_effective_filterable_category_attributes(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $brakes = $this->category('Brakes', 'brakes', $root->id);

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
        $bosch = AttributeValue::create([
            'attribute_id' => $brand->id,
            'label' => 'Bosch',
            'value' => 'bosch',
        ]);

        $root->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);
        $root->attributes()->attach($material->id, [
            'is_filterable' => false,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $bremboProduct = $this->product('Brembo brake pad', 'brembo-brake-pad');
        $bremboProduct->categories()->attach($brakes);
        $bremboProduct->attributeValues()->attach($brembo, [
            'attribute_id' => $brand->id,
        ]);

        $boschProduct = $this->product('Bosch brake pad', 'bosch-brake-pad');
        $boschProduct->categories()->attach($brakes);
        $boschProduct->attributeValues()->attach($bosch, [
            'attribute_id' => $brand->id,
        ]);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brembo-brake-pad');

        $this->getJson('/api/products?brand=brembo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');

        $this->getJson('/api/products?category=brakes&material=steel')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('material');

        $this->getJson('/api/products?category=brakes&brand=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');
    }

    public function test_product_filters_match_active_variant_attribute_values(): void
    {
        $category = $this->category('Brakes', 'brakes');
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

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $product = $this->product('Brake pad set', 'brake-pad-set');
        $product->categories()->attach($category);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'brand-brembo',
            'stock' => 3,
            'is_active' => true,
        ]);
        $variant->attributeValues()->attach($brembo);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-pad-set');
    }

    /* ────────────────────────────────────────────────────────────
     | Category filter
     | ──────────────────────────────────────────────────────────── */

    public function test_category_filter_includes_descendant_products(): void
    {
        $root = $this->category('Car parts', 'car-parts');
        $brakes = $this->category('Brakes', 'brakes', $root->id);
        $discs = $this->category('Discs', 'discs', $brakes->id);

        $p1 = $this->product('Pad', 'pad');
        $p1->categories()->attach($brakes);

        $p2 = $this->product('Disc', 'disc');
        $p2->categories()->attach($discs);

        $p3 = $this->product('Filter', 'filter');
        $p3->categories()->attach($root);

        $this->getJson('/api/products?category=brakes')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_multiple_category_slugs_are_supported(): void
    {
        $brakes = $this->category('Brakes', 'brakes');
        $lights = $this->category('Lights', 'lights');

        $p1 = $this->product('Pad', 'pad');
        $p1->categories()->attach($brakes);

        $p2 = $this->product('Bulb', 'bulb');
        $p2->categories()->attach($lights);

        $this->getJson('/api/products?categories=brakes,lights')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /* ────────────────────────────────────────────────────────────
     | Attribute filter: multiple values of same attribute = OR
     | ──────────────────────────────────────────────────────────── */

    public function test_multiple_values_of_same_attribute_are_ored(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);
        $bosch = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Bosch', 'value' => 'bosch']);
        $trw = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'TRW', 'value' => 'trw']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $p1 = $this->product('Brembo pad', 'brembo-pad');
        $p1->categories()->attach($category);
        $p1->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);

        $p2 = $this->product('Bosch pad', 'bosch-pad');
        $p2->categories()->attach($category);
        $p2->attributeValues()->attach($bosch, ['attribute_id' => $brand->id]);

        $p3 = $this->product('TRW pad', 'trw-pad');
        $p3->categories()->attach($category);
        $p3->attributeValues()->attach($trw, ['attribute_id' => $brand->id]);

        $this->getJson('/api/products?category=brakes&brand=brembo,bosch')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /* ────────────────────────────────────────────────────────────
     | Attribute filter: multiple different attributes = AND
     | ──────────────────────────────────────────────────────────── */

    public function test_different_attributes_are_anded(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $color = Attribute::create(['name' => 'Color', 'slug' => 'color', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);
        $red = AttributeValue::create(['attribute_id' => $color->id, 'label' => 'Red', 'value' => 'red']);
        $black = AttributeValue::create(['attribute_id' => $color->id, 'label' => 'Black', 'value' => 'black']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);
        $category->attributes()->attach($color->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $p1 = $this->product('Red Brembo', 'red-brembo');
        $p1->categories()->attach($category);
        $p1->attributeValues()->attach($brembo->id, ['attribute_id' => $brand->id]);
        $p1->attributeValues()->attach($red->id, ['attribute_id' => $color->id]);

        $p2 = $this->product('Black Brembo', 'black-brembo');
        $p2->categories()->attach($category);
        $p2->attributeValues()->attach($brembo->id, ['attribute_id' => $brand->id]);
        $p2->attributeValues()->attach($black->id, ['attribute_id' => $color->id]);

        $p3 = $this->product('Red Bosch', 'red-bosch');
        $p3->categories()->attach($category);

        // brand=brembo AND color=red
        $this->getJson('/api/products?category=brakes&brand=brembo&color=red')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'red-brembo');
    }

    /* ────────────────────────────────────────────────────────────
     | Variant attribute filter
     | ──────────────────────────────────────────────────────────── */

    public function test_variant_attribute_filter_matches_active_variants(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $product = $this->product('Brake set', 'brake-set');
        $product->categories()->attach($category);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'brembo',
            'stock' => 5,
            'is_active' => true,
        ]);
        $variant->attributeValues()->attach($brembo);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-set');
    }

    public function test_inactive_variant_is_not_matched(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $product = $this->product('Brake set', 'brake-set');
        $product->categories()->attach($category);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'brembo',
            'stock' => 5,
            'is_active' => false,
        ]);
        $variant->attributeValues()->attach($brembo);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /* ────────────────────────────────────────────────────────────
     | Price filter
     | ──────────────────────────────────────────────────────────── */

    public function test_price_min_filters_products(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('Cheap part', 'cheap-part');
        $p1->update(['price' => 50]);
        $p1->categories()->attach($category);

        $p2 = $this->product('Expensive part', 'expensive-part');
        $p2->update(['price' => 200]);
        $p2->categories()->attach($category);

        $this->getJson('/api/products?category=parts&price_min=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'expensive-part');
    }

    public function test_price_max_filters_products(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('Cheap part', 'cheap-part');
        $p1->update(['price' => 50]);
        $p1->categories()->attach($category);

        $p2 = $this->product('Expensive part', 'expensive-part');
        $p2->update(['price' => 200]);
        $p2->categories()->attach($category);

        $this->getJson('/api/products?category=parts&price_max=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'cheap-part');
    }

    public function test_price_range_filters_products(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('Cheap', 'cheap');
        $p1->update(['price' => 10]);
        $p1->categories()->attach($category);

        $p2 = $this->product('Mid', 'mid');
        $p2->update(['price' => 100]);
        $p2->categories()->attach($category);

        $p3 = $this->product('Expensive', 'expensive');
        $p3->update(['price' => 500]);
        $p3->categories()->attach($category);

        $this->getJson('/api/products?category=parts&price_min=50&price_max=200')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'mid');
    }

    public function test_price_filter_considers_variant_effective_price(): void
    {
        $category = $this->category('Parts', 'parts');
        $product = $this->product('Product A', 'product-a');
        $product->update(['price' => 300]);
        $product->categories()->attach($category);

        ProductVariant::create([
            'product_id' => $product->id,
            'price' => 50,
            'stock' => 1,
            'is_active' => true,
            'combination_key' => 'v1',
        ]);

        // Product base price is 300, but variant effective price is 50
        $this->getJson('/api/products?category=parts&price_min=40&price_max=60')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'product-a');
    }

    public function test_invalid_price_returns_validation_error(): void
    {
        $this->getJson('/api/products?price_min=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_min');
    }

    /* ────────────────────────────────────────────────────────────
     | in_stock filter
     | ──────────────────────────────────────────────────────────── */

    public function test_in_stock_true_returns_only_in_stock_products(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('In stock', 'in-stock');
        $p1->update(['stock' => 10]);
        $p1->categories()->attach($category);

        $p2 = $this->product('Out of stock', 'out-of-stock');
        $p2->update(['stock' => 0]);
        $p2->categories()->attach($category);

        $this->getJson('/api/products?category=parts&in_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'in-stock');
    }

    public function test_in_stock_false_returns_only_out_of_stock_products(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('In stock', 'in-stock');
        $p1->update(['stock' => 10]);
        $p1->categories()->attach($category);

        $p2 = $this->product('Out of stock', 'out-of-stock');
        $p2->update(['stock' => 0]);
        $p2->categories()->attach($category);

        $this->getJson('/api/products?category=parts&in_stock=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'out-of-stock');
    }

    public function test_in_stock_with_variants_considers_active_variant_stock(): void
    {
        $category = $this->category('Parts', 'parts');

        $p1 = $this->product('With stock variant', 'stock-variant');
        $p1->categories()->attach($category);
        ProductVariant::create([
            'product_id' => $p1->id,
            'stock' => 5,
            'is_active' => true,
            'combination_key' => 'v1',
        ]);

        $p2 = $this->product('Empty variant', 'empty-variant');
        $p2->categories()->attach($category);
        ProductVariant::create([
            'product_id' => $p2->id,
            'stock' => 0,
            'is_active' => true,
            'combination_key' => 'v1',
        ]);

        $p3 = $this->product('Inactive variant only', 'inactive-variant');
        $p3->categories()->attach($category);
        ProductVariant::create([
            'product_id' => $p3->id,
            'stock' => 10,
            'is_active' => false,
            'combination_key' => 'v1',
        ]);

        $this->getJson('/api/products?category=parts&in_stock=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'stock-variant');
    }

    /* ────────────────────────────────────────────────────────────
     | Vehicle compatibility filter
     | ──────────────────────────────────────────────────────────── */

    public function test_vehicle_engine_filter(): void
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $model = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $model->id, 'year_start' => 2019, 'is_active' => true]);
        $trim = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $engine = VehicleEngine::create(['name' => '2.0L', 'slug' => '2-0l', 'vehicle_trim_id' => $trim->id, 'is_active' => true]);

        $p1 = $this->product('Compatible part', 'compatible-part');
        $p1->vehicleEngines()->attach($engine);

        $p2 = $this->product('Other part', 'other-part');

        $this->getJson("/api/products?vehicle_engine_id={$engine->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'compatible-part');
    }

    public function test_vehicle_brand_filter(): void
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $model = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $model->id, 'year_start' => 2019, 'is_active' => true]);
        $trim = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $engine = VehicleEngine::create(['name' => '2.0L', 'slug' => '2-0l', 'vehicle_trim_id' => $trim->id, 'is_active' => true]);

        $p1 = $this->product('Toyota part', 'toyota-part');
        $p1->vehicleEngines()->attach($engine);

        $p2 = $this->product('Generic part', 'generic-part');

        $this->getJson("/api/products?vehicle_brand_id={$brand->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'toyota-part');
    }

    public function test_vehicle_model_filter(): void
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $corolla = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $camry = VehicleModel::create(['name' => 'Camry', 'slug' => 'camry', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen1 = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $corolla->id, 'year_start' => 2019, 'is_active' => true]);
        $gen2 = VehicleGeneration::create(['name' => 'XV70', 'slug' => 'xv70', 'vehicle_model_id' => $camry->id, 'year_start' => 2018, 'is_active' => true]);
        $trim1 = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen1->id, 'is_active' => true]);
        $trim2 = VehicleTrim::create(['name' => 'LE', 'slug' => 'le', 'vehicle_generation_id' => $gen2->id, 'is_active' => true]);
        $engine1 = VehicleEngine::create(['name' => 'Corolla Engine', 'slug' => 'corolla-engine', 'vehicle_trim_id' => $trim1->id, 'is_active' => true]);
        $engine2 = VehicleEngine::create(['name' => 'Camry Engine', 'slug' => 'camry-engine', 'vehicle_trim_id' => $trim2->id, 'is_active' => true]);

        $p1 = $this->product('Corolla part', 'corolla-part');
        $p1->vehicleEngines()->attach($engine1);

        $p2 = $this->product('Camry part', 'camry-part');
        $p2->vehicleEngines()->attach($engine2);

        $this->getJson("/api/products?vehicle_model_id={$corolla->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'corolla-part');
    }

    public function test_vehicle_generation_filter(): void
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $model = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen1 = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $model->id, 'year_start' => 2019, 'is_active' => true]);
        $gen2 = VehicleGeneration::create(['name' => 'E180', 'slug' => 'e180', 'vehicle_model_id' => $model->id, 'year_start' => 2013, 'is_active' => true]);
        $trim1 = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen1->id, 'is_active' => true]);
        $trim2 = VehicleTrim::create(['name' => 'LE', 'slug' => 'le', 'vehicle_generation_id' => $gen2->id, 'is_active' => true]);
        $engine1 = VehicleEngine::create(['name' => 'E210 Engine', 'slug' => 'e210-engine', 'vehicle_trim_id' => $trim1->id, 'is_active' => true]);
        $engine2 = VehicleEngine::create(['name' => 'E180 Engine', 'slug' => 'e180-engine', 'vehicle_trim_id' => $trim2->id, 'is_active' => true]);

        $p1 = $this->product('E210 part', 'e210-part');
        $p1->vehicleEngines()->attach($engine1);

        $p2 = $this->product('E180 part', 'e180-part');
        $p2->vehicleEngines()->attach($engine2);

        $this->getJson("/api/products?vehicle_generation_id={$gen1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'e210-part');
    }

    public function test_vehicle_trim_filter(): void
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $model = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand->id, 'is_active' => true]);
        $gen = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $model->id, 'year_start' => 2019, 'is_active' => true]);
        $trim1 = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $trim2 = VehicleTrim::create(['name' => 'LE', 'slug' => 'le', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $engine1 = VehicleEngine::create(['name' => 'SE Engine', 'slug' => 'se-engine', 'vehicle_trim_id' => $trim1->id, 'is_active' => true]);
        $engine2 = VehicleEngine::create(['name' => 'LE Engine', 'slug' => 'le-engine', 'vehicle_trim_id' => $trim2->id, 'is_active' => true]);

        $p1 = $this->product('SE part', 'se-part');
        $p1->vehicleEngines()->attach($engine1);

        $p2 = $this->product('LE part', 'le-part');
        $p2->vehicleEngines()->attach($engine2);

        $this->getJson("/api/products?vehicle_trim_id={$trim1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'se-part');
    }

    /* ────────────────────────────────────────────────────────────
     | Search
     | ──────────────────────────────────────────────────────────── */

    public function test_search_matches_name(): void
    {
        $p1 = $this->product('Brake pad', 'brake-pad');
        $p2 = $this->product('Headlight bulb', 'headlight-bulb');

        $this->getJson('/api/products?search=brake')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-pad');
    }

    public function test_search_matches_sku(): void
    {
        $p1 = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'sku' => 'BP-001',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?search=BP-001')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-pad');
    }

    public function test_search_matches_short_description(): void
    {
        $p1 = Product::create([
            'name' => 'Brake pad',
            'slug' => 'brake-pad',
            'short_description' => 'High-performance ceramic brake pad',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->getJson('/api/products?search=ceramic')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_combines_with_other_filters(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $p1 = $this->product('Brembo brake pad', 'brembo-brake-pad');
        $p1->categories()->attach($category);
        $p1->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);

        $p2 = $this->product('Bosch brake pad', 'bosch-brake-pad');
        $p2->categories()->attach($category);

        $this->getJson('/api/products?category=brakes&search=brake&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brembo-brake-pad');
    }

    /* ────────────────────────────────────────────────────────────
     | Sorting
     | ──────────────────────────────────────────────────────────── */

    public function test_sort_by_price_asc(): void
    {
        $p1 = $this->product('Expensive', 'expensive');
        $p1->update(['price' => 200]);
        $p2 = $this->product('Cheap', 'cheap');
        $p2->update(['price' => 50]);

        $response = $this->getJson('/api/products?sort=price_asc')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->values()->all();
        $this->assertEquals(['cheap', 'expensive'], $slugs);
    }

    public function test_sort_by_price_desc(): void
    {
        $p1 = $this->product('Expensive', 'expensive');
        $p1->update(['price' => 200]);
        $p2 = $this->product('Cheap', 'cheap');
        $p2->update(['price' => 50]);

        $response = $this->getJson('/api/products?sort=price_desc')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->values()->all();
        $this->assertEquals(['expensive', 'cheap'], $slugs);
    }

    public function test_sort_by_name_asc(): void
    {
        $this->product('Zebra', 'zebra');
        $this->product('Apple', 'apple');

        $response = $this->getJson('/api/products?sort=name_asc')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->values()->all();
        $this->assertEquals(['apple', 'zebra'], $slugs);
    }

    public function test_sort_by_name_desc(): void
    {
        $this->product('Apple', 'apple');
        $this->product('Zebra', 'zebra');

        $response = $this->getJson('/api/products?sort=name_desc')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->values()->all();
        $this->assertEquals(['zebra', 'apple'], $slugs);
    }

    public function test_invalid_sort_returns_validation_error(): void
    {
        $this->getJson('/api/products?sort=random_field')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_default_sort_is_newest(): void
    {
        $p1 = $this->product('Old', 'old');
        $p1->update(['published_at' => now()->subDays(2), 'created_at' => now()->subDays(2)]);

        $p2 = $this->product('New', 'new');
        $p2->update(['published_at' => now(), 'created_at' => now()]);

        $response = $this->getJson('/api/products')->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->values()->all();
        $this->assertEquals(['new', 'old'], $slugs);
    }

    /* ────────────────────────────────────────────────────────────
     | Pagination
     | ──────────────────────────────────────────────────────────── */

    public function test_pagination_default_is_12(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->product("Product {$i}", "product-{$i}");
        }

        $response = $this->getJson('/api/products')->assertOk();
        $this->assertCount(12, $response->json('data'));
        $this->assertEquals(15, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_per_page_is_respected(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->product("Product {$i}", "product-{$i}");
        }

        $response = $this->getJson('/api/products?per_page=5')->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.last_page'));
    }

    public function test_per_page_max_is_48(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->product("Product {$i}", "product-{$i}");
        }

        $response = $this->getJson('/api/products?per_page=100')->assertOk();
        $this->assertCount(48, $response->json('data'));
    }

    /* ────────────────────────────────────────────────────────────
     | Combined filters
     | ──────────────────────────────────────────────────────────── */

    public function test_multiple_filters_work_together(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);
        $bosch = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Bosch', 'value' => 'bosch']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $brand2 = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota', 'is_active' => true]);
        $model = VehicleModel::create(['name' => 'Corolla', 'slug' => 'corolla', 'vehicle_brand_id' => $brand2->id, 'is_active' => true]);
        $gen = VehicleGeneration::create(['name' => 'E210', 'slug' => 'e210', 'vehicle_model_id' => $model->id, 'year_start' => 2019, 'is_active' => true]);
        $trim = VehicleTrim::create(['name' => 'SE', 'slug' => 'se', 'vehicle_generation_id' => $gen->id, 'is_active' => true]);
        $engine = VehicleEngine::create(['name' => '2.0L', 'slug' => '2-0l', 'vehicle_trim_id' => $trim->id, 'is_active' => true]);

        // Match: category + attribute + vehicle + price + in_stock + search
        $p1 = $this->product('Brembo Toyota brake pad', 'brembo-toyota-brake-pad');
        $p1->update(['price' => 150, 'stock' => 5]);
        $p1->categories()->attach($category);
        $p1->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);
        $p1->vehicleEngines()->attach($engine);

        // Wrong brand
        $p2 = $this->product('Bosch Toyota brake pad', 'bosch-toyota-brake-pad');
        $p2->update(['price' => 150, 'stock' => 5]);
        $p2->categories()->attach($category);
        $p2->attributeValues()->attach($bosch, ['attribute_id' => $brand->id]);
        $p2->vehicleEngines()->attach($engine);

        // Wrong price
        $p3 = $this->product('Brembo Toyota expensive pad', 'brembo-toyota-expensive');
        $p3->update(['price' => 500, 'stock' => 5]);
        $p3->categories()->attach($category);
        $p3->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);
        $p3->vehicleEngines()->attach($engine);

        // Out of stock
        $p4 = $this->product('Brembo Toyota out-of-stock pad', 'brembo-toyota-oos');
        $p4->update(['price' => 150, 'stock' => 0]);
        $p4->categories()->attach($category);
        $p4->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);
        $p4->vehicleEngines()->attach($engine);

        $this->getJson('/api/products?category=brakes&brand=brembo&vehicle_brand_id=' . $brand2->id . '&price_min=100&price_max=200&in_stock=1&search=brake')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brembo-toyota-brake-pad');
    }

    /* ────────────────────────────────────────────────────────────
     | No duplicate products
     | ──────────────────────────────────────────────────────────── */

    public function test_no_duplicate_products_with_variants(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);
        $bosch = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Bosch', 'value' => 'bosch']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => true,
        ]);

        $product = $this->product('Brake pad set', 'brake-pad-set');
        $product->categories()->attach($category);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'brembo',
            'stock' => 3,
            'is_active' => true,
        ]);
        $v1->attributeValues()->attach($brembo);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'combination_key' => 'bosch',
            'stock' => 2,
            'is_active' => true,
        ]);
        $v2->attributeValues()->attach($bosch);

        $this->getJson('/api/products?category=brakes&brand=brembo,bosch')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brake-pad-set');
    }

    /* ────────────────────────────────────────────────────────────
     | Product without variants
     | ──────────────────────────────────────────────────────────── */

    public function test_product_without_variants_matches_attribute_filter(): void
    {
        $category = $this->category('Brakes', 'brakes');
        $brand = Attribute::create(['name' => 'Brand', 'slug' => 'brand', 'type' => 'select']);
        $brembo = AttributeValue::create(['attribute_id' => $brand->id, 'label' => 'Brembo', 'value' => 'brembo']);

        $category->attributes()->attach($brand->id, [
            'is_filterable' => true,
            'is_required' => false,
            'is_variant_axis' => false,
        ]);

        $product = $this->product('Brembo pad', 'brembo-pad');
        $product->categories()->attach($category);
        $product->attributeValues()->attach($brembo, ['attribute_id' => $brand->id]);

        $this->getJson('/api/products?category=brakes&brand=brembo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'brembo-pad');
    }

    public function test_product_with_multiple_variants_and_variant_stock_filter(): void
    {
        $category = $this->category('Parts', 'parts');
        $product = $this->product('Multi variant', 'multi-variant');
        $product->categories()->attach($category);

        ProductVariant::create([
            'product_id' => $product->id,
            'stock' => 0,
            'is_active' => true,
            'combination_key' => 'v1',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'stock' => 3,
            'is_active' => true,
            'combination_key' => 'v2',
        ]);

        $this->getJson('/api/products?category=parts&in_stock=1')
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
