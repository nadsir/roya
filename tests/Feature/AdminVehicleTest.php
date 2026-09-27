<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminVehicleTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]), ['admin']);
    }

    private function createBrand(string $suffix = ''): VehicleBrand
    {
        return VehicleBrand::create([
            'name' => 'Toyota' . $suffix,
            'slug' => 'toyota' . $suffix,
        ]);
    }

    private function createModel(VehicleBrand $brand, string $suffix = ''): VehicleModel
    {
        return $brand->models()->create([
            'name' => 'Corolla' . $suffix,
            'slug' => 'corolla' . $suffix,
        ]);
    }

    private function createGeneration(VehicleModel $model, string $suffix = ''): VehicleGeneration
    {
        return $model->generations()->create([
            'name' => 'E210' . $suffix,
            'slug' => 'e210' . $suffix,
            'year_start' => 2019,
            'year_end' => 2023,
        ]);
    }

    private function createTrim(VehicleGeneration $generation, string $suffix = ''): VehicleTrim
    {
        return $generation->trims()->create([
            'name' => 'SE' . $suffix,
            'slug' => 'se' . $suffix,
        ]);
    }

    private function createEngine(VehicleTrim $trim, string $suffix = ''): VehicleEngine
    {
        return $trim->engines()->create([
            'name' => '2.0L' . $suffix,
            'slug' => '2l' . $suffix,
            'displacement' => 2.0,
            'fuel_type' => 'gasoline',
            'horsepower' => 169,
        ]);
    }

    // ── Brand CRUD ─────────────────────────────────────────────────

    public function test_admin_can_create_brand(): void
    {
        $this->authenticate();

        $this->postJson('/api/admin/vehicles/brands', [
            'name' => 'Toyota',
            'slug' => 'toyota',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Toyota')
            ->assertJsonPath('slug', 'toyota');

        $this->assertDatabaseHas('vehicle_brands', ['slug' => 'toyota']);
    }

    public function test_admin_can_update_brand(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();

        $this->putJson("/api/admin/vehicles/brands/{$brand->id}", [
            'name' => 'Toyota Updated',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Toyota Updated');

        $this->assertDatabaseHas('vehicle_brands', ['id' => $brand->id, 'name' => 'Toyota Updated']);
    }

    public function test_admin_can_delete_brand(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicle_brands', ['id' => $brand->id]);
    }

    public function test_brand_with_models_cannot_be_deleted(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $this->createModel($brand);

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');
    }

    // ── Model CRUD ─────────────────────────────────────────────────

    public function test_admin_can_create_model_under_brand(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();

        $this->postJson("/api/admin/vehicles/brands/{$brand->id}/models", [
            'name' => 'Corolla',
            'slug' => 'corolla',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Corolla')
            ->assertJsonPath('vehicle_brand_id', $brand->id);

        $this->assertDatabaseHas('vehicle_models', ['slug' => 'corolla', 'vehicle_brand_id' => $brand->id]);
    }

    public function test_admin_can_update_model(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);

        $this->putJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}", [
            'name' => 'Camry',
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Camry');

        $this->assertDatabaseHas('vehicle_models', ['id' => $model->id, 'name' => 'Camry']);
    }

    public function test_admin_can_delete_model(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicle_models', ['id' => $model->id]);
    }

    public function test_model_with_generations_cannot_be_deleted(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $this->createGeneration($model);

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('model');
    }

    // ── Generation CRUD ────────────────────────────────────────────

    public function test_admin_can_create_generation_under_model(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);

        $this->postJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations", [
            'name' => 'E210',
            'slug' => 'e210',
            'year_start' => 2019,
            'year_end' => 2023,
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'E210')
            ->assertJsonPath('year_start', 2019)
            ->assertJsonPath('year_end', 2023);

        $this->assertDatabaseHas('vehicle_generations', ['slug' => 'e210']);
    }

    public function test_admin_can_update_generation(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);

        $this->putJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}", [
            'year_end' => 2024,
        ])
            ->assertOk()
            ->assertJsonPath('year_end', 2024);

        $this->assertDatabaseHas('vehicle_generations', ['id' => $generation->id, 'year_end' => 2024]);
    }

    public function test_admin_can_delete_generation(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicle_generations', ['id' => $generation->id]);
    }

    public function test_generation_with_trims_cannot_be_deleted(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $this->createTrim($generation);

        $this->deleteJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('generation');
    }

    // ── Trim CRUD ──────────────────────────────────────────────────

    public function test_admin_can_create_trim_under_generation(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);

        $this->postJson("/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims", [
            'name' => 'SE',
            'slug' => 'se',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'SE')
            ->assertJsonPath('vehicle_generation_id', $generation->id);

        $this->assertDatabaseHas('vehicle_trims', ['slug' => 'se']);
    }

    public function test_admin_can_update_trim(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);

        $this->putJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}",
            ['name' => 'XSE']
        )
            ->assertOk()
            ->assertJsonPath('name', 'XSE');

        $this->assertDatabaseHas('vehicle_trims', ['id' => $trim->id, 'name' => 'XSE']);
    }

    public function test_admin_can_delete_trim(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);

        $this->deleteJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}"
        )
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicle_trims', ['id' => $trim->id]);
    }

    public function test_trim_with_engines_cannot_be_deleted(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);
        $this->createEngine($trim);

        $this->deleteJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}"
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('trim');
    }

    // ── Engine CRUD ────────────────────────────────────────────────

    public function test_admin_can_create_engine_under_trim(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);

        $this->postJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}/engines",
            [
                'name' => '2.0L 4-Cylinder',
                'slug' => '2l-4cyl',
                'displacement' => 2.0,
                'fuel_type' => 'gasoline',
                'horsepower' => 169,
            ]
        )
            ->assertCreated()
            ->assertJsonPath('name', '2.0L 4-Cylinder')
            ->assertJsonPath('fuel_type', 'gasoline')
            ->assertJsonPath('horsepower', 169)
            ->assertJsonFragment(['displacement' => '2.0']);

        $this->assertDatabaseHas('vehicle_engines', ['slug' => '2l-4cyl']);
    }

    public function test_admin_can_create_engine_with_nullable_fields(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);

        $this->postJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}/engines",
            [
                'name' => 'Electric',
                'slug' => 'electric',
            ]
        )
            ->assertCreated()
            ->assertJsonPath('displacement', null)
            ->assertJsonPath('fuel_type', null)
            ->assertJsonPath('horsepower', null);
    }

    public function test_admin_can_update_engine(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);
        $engine = $this->createEngine($trim);

        $this->putJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}/engines/{$engine->id}",
            ['horsepower' => 200]
        )
            ->assertOk()
            ->assertJsonPath('horsepower', 200);

        $this->assertDatabaseHas('vehicle_engines', ['id' => $engine->id, 'horsepower' => 200]);
    }

    public function test_admin_can_delete_engine(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trim = $this->createTrim($generation);
        $engine = $this->createEngine($trim);

        $this->deleteJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trim->id}/engines/{$engine->id}"
        )
            ->assertNoContent();

        $this->assertDatabaseMissing('vehicle_engines', ['id' => $engine->id]);
    }

    // ── Invalid parent relationships ───────────────────────────────

    public function test_model_under_wrong_brand_returns_404(): void
    {
        $this->authenticate();
        $brandA = $this->createBrand('-A');
        $brandB = $this->createBrand('-B');
        $model = $this->createModel($brandA);

        $this->getJson("/api/admin/vehicles/brands/{$brandB->id}/models/{$model->id}")
            ->assertNotFound();
    }

    public function test_generation_under_wrong_model_returns_404(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $modelA = $this->createModel($brand, '-A');
        $modelB = $this->createModel($brand, '-B');
        $generation = $this->createGeneration($modelA);

        $this->getJson("/api/admin/vehicles/brands/{$brand->id}/models/{$modelB->id}/generations/{$generation->id}")
            ->assertNotFound();
    }

    public function test_trim_under_wrong_generation_returns_404(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $genA = $this->createGeneration($model, '-A');
        $genB = $this->createGeneration($model, '-B');
        $trim = $this->createTrim($genA);

        $this->getJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$genB->id}/trims/{$trim->id}"
        )
            ->assertNotFound();
    }

    public function test_engine_under_wrong_trim_returns_404(): void
    {
        $this->authenticate();
        $brand = $this->createBrand();
        $model = $this->createModel($brand);
        $generation = $this->createGeneration($model);
        $trimA = $this->createTrim($generation, '-A');
        $trimB = $this->createTrim($generation, '-B');
        $engine = $this->createEngine($trimA);

        $this->getJson(
            "/api/admin/vehicles/brands/{$brand->id}/models/{$model->id}/generations/{$generation->id}/trims/{$trimB->id}/engines/{$engine->id}"
        )
            ->assertNotFound();
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/admin/vehicles/brands')
            ->assertUnauthorized();
    }

    // ── Product ↔ Vehicle Compatibility ───────────────────────────

    private function createProduct(string $suffix = ''): Product
    {
        return Product::create([
            'name' => 'Brake Pad' . $suffix,
            'slug' => 'brake-pad' . $suffix,
            'price' => 25.99,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_attach_engine_to_product(): void
    {
        $this->authenticate();
        $h = $this->createFullHierarchy();
        $product = $this->createProduct();

        $this->postJson("/api/admin/products/{$product->id}/vehicle-compat", [
            'vehicle_engine_ids' => [$h['engine']->id],
        ])
            ->assertOk()
            ->assertJsonPath('total_compatible_engines', 1);

        $this->assertDatabaseHas('product_vehicle_compat', [
            'product_id' => $product->id,
            'vehicle_engine_id' => $h['engine']->id,
        ]);
    }

    public function test_admin_can_list_product_compatibility(): void
    {
        $this->authenticate();
        $h = $this->createFullHierarchy();
        $product = $this->createProduct();
        $product->vehicleEngines()->attach($h['engine']->id);

        $this->getJson("/api/admin/products/{$product->id}/vehicle-compat")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.engine.slug', $h['engine']->slug)
            ->assertJsonPath('data.0.trim.slug', $h['trim']->slug)
            ->assertJsonPath('data.0.generation.slug', $h['generation']->slug)
            ->assertJsonPath('data.0.model.slug', $h['model']->slug)
            ->assertJsonPath('data.0.brand.slug', $h['brand']->slug);
    }

    public function test_admin_can_remove_compatibility(): void
    {
        $this->authenticate();
        $h = $this->createFullHierarchy();
        $product = $this->createProduct();
        $product->vehicleEngines()->attach($h['engine']->id);

        $this->deleteJson("/api/admin/products/{$product->id}/vehicle-compat/{$h['engine']->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_vehicle_compat', [
            'product_id' => $product->id,
            'vehicle_engine_id' => $h['engine']->id,
        ]);
    }

    public function test_duplicate_compatibility_is_safely_handled(): void
    {
        $this->authenticate();
        $h = $this->createFullHierarchy();
        $product = $this->createProduct();
        $product->vehicleEngines()->attach($h['engine']->id);

        $this->postJson("/api/admin/products/{$product->id}/vehicle-compat", [
            'vehicle_engine_ids' => [$h['engine']->id],
        ])
            ->assertOk()
            ->assertJsonPath('total_compatible_engines', 1);
    }

    public function test_invalid_engine_id_is_rejected(): void
    {
        $this->authenticate();
        $product = $this->createProduct();

        $this->postJson("/api/admin/products/{$product->id}/vehicle-compat", [
            'vehicle_engine_ids' => [999999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_engine_ids.0');
    }

    public function test_compatibility_requires_admin_auth(): void
    {
        $product = $this->createProduct();

        $this->postJson("/api/admin/products/{$product->id}/vehicle-compat", [
            'vehicle_engine_ids' => [1],
        ])
            ->assertUnauthorized();
    }

    private function createFullHierarchy(): array
    {
        $brand = VehicleBrand::create(['name' => 'Toyota', 'slug' => 'toyota']);

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
            'name' => '2.0L',
            'slug' => '2l',
            'displacement' => 2.0,
            'fuel_type' => 'gasoline',
            'horsepower' => 169,
        ]);

        return compact('brand', 'model', 'generation', 'trim', 'engine');
    }
}
