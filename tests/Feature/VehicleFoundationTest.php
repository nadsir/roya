<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function createHierarchy(): array
    {
        $brand = VehicleBrand::create([
            'name' => 'Toyota',
            'slug' => 'toyota',
        ]);

        $model = VehicleModel::create([
            'vehicle_brand_id' => $brand->id,
            'name' => 'Corolla',
            'slug' => 'corolla',
        ]);

        $generation = VehicleGeneration::create([
            'vehicle_model_id' => $model->id,
            'name' => 'E210',
            'slug' => 'e210',
            'year_start' => 2019,
            'year_end' => 2023,
        ]);

        $trim = VehicleTrim::create([
            'vehicle_generation_id' => $generation->id,
            'name' => 'SE',
            'slug' => 'se',
        ]);

        $engine = VehicleEngine::create([
            'vehicle_trim_id' => $trim->id,
            'name' => '2.0L 4-Cylinder',
            'slug' => '2l-4cyl',
            'displacement' => 2.0,
            'fuel_type' => 'gasoline',
            'horsepower' => 169,
        ]);

        return compact('brand', 'model', 'generation', 'trim', 'engine');
    }

    public function test_hierarchy_can_be_created_with_valid_foreign_keys(): void
    {
        $h = $this->createHierarchy();

        $this->assertDatabaseHas('vehicle_brands', ['slug' => 'toyota']);
        $this->assertDatabaseHas('vehicle_models', ['slug' => 'corolla', 'vehicle_brand_id' => $h['brand']->id]);
        $this->assertDatabaseHas('vehicle_generations', ['slug' => 'e210', 'vehicle_model_id' => $h['model']->id]);
        $this->assertDatabaseHas('vehicle_trims', ['slug' => 'se', 'vehicle_generation_id' => $h['generation']->id]);
        $this->assertDatabaseHas('vehicle_engines', ['slug' => '2l-4cyl', 'vehicle_trim_id' => $h['trim']->id]);

        $this->assertEquals('Toyota', $h['brand']->name);
        $this->assertEquals('Corolla', $h['model']->name);
        $this->assertEquals('E210', $h['generation']->name);
        $this->assertEquals('SE', $h['trim']->name);
        $this->assertEquals(2.0, $h['engine']->displacement);
    }

    public function test_deleting_brand_cascades_to_engines(): void
    {
        $h = $this->createHierarchy();

        $h['brand']->delete();

        $this->assertDatabaseMissing('vehicle_brands', ['id' => $h['brand']->id]);
        $this->assertDatabaseMissing('vehicle_models', ['id' => $h['model']->id]);
        $this->assertDatabaseMissing('vehicle_generations', ['id' => $h['generation']->id]);
        $this->assertDatabaseMissing('vehicle_trims', ['id' => $h['trim']->id]);
        $this->assertDatabaseMissing('vehicle_engines', ['id' => $h['engine']->id]);
    }

    public function test_deleting_generation_cascades_to_engines(): void
    {
        $h = $this->createHierarchy();

        $h['generation']->delete();

        $this->assertDatabaseMissing('vehicle_generations', ['id' => $h['generation']->id]);
        $this->assertDatabaseMissing('vehicle_trims', ['id' => $h['trim']->id]);
        $this->assertDatabaseMissing('vehicle_engines', ['id' => $h['engine']->id]);
        $this->assertDatabaseHas('vehicle_brands', ['id' => $h['brand']->id]);
        $this->assertDatabaseHas('vehicle_models', ['id' => $h['model']->id]);
    }

    private function createProduct(string $suffix = ''): Product
    {
        return Product::create([
            'name' => 'Brake Pad' . $suffix,
            'slug' => 'brake-pad' . $suffix,
            'price' => 25.99,
            'is_active' => true,
        ]);
    }

    public function test_product_can_be_attached_to_vehicle_engine(): void
    {
        $h = $this->createHierarchy();
        $product = $this->createProduct();

        $product->vehicleEngines()->attach($h['engine']->id);

        $this->assertDatabaseHas('product_vehicle_compat', [
            'product_id' => $product->id,
            'vehicle_engine_id' => $h['engine']->id,
        ]);

        $this->assertCount(1, $product->vehicleEngines);
        $this->assertEquals($h['engine']->id, $product->vehicleEngines->first()->id);
    }

    public function test_vehicle_engine_belongs_to_product(): void
    {
        $h = $this->createHierarchy();
        $product = $this->createProduct();

        $h['engine']->products()->attach($product->id);

        $this->assertCount(1, $h['engine']->products);
        $this->assertEquals($product->id, $h['engine']->products->first()->id);
    }

    public function test_duplicate_product_engine_compat_is_rejected(): void
    {
        $h = $this->createHierarchy();
        $product = $this->createProduct();

        $product->vehicleEngines()->attach($h['engine']->id);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $product->vehicleEngines()->attach($h['engine']->id);
    }

    public function test_deleting_product_cascades_compat(): void
    {
        $h = $this->createHierarchy();
        $product = $this->createProduct();

        $product->vehicleEngines()->attach($h['engine']->id);

        $product->delete();

        $this->assertDatabaseMissing('product_vehicle_compat', [
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('vehicle_engines', ['id' => $h['engine']->id]);
    }

    public function test_deleting_engine_cascades_compat(): void
    {
        $h = $this->createHierarchy();
        $product = $this->createProduct();

        $product->vehicleEngines()->attach($h['engine']->id);

        $h['engine']->delete();

        $this->assertDatabaseMissing('product_vehicle_compat', [
            'vehicle_engine_id' => $h['engine']->id,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }
}
