<?php

namespace Tests\Feature;

use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleApiTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveHierarchy(): array
    {
        $brand = VehicleBrand::create([
            'name' => 'Toyota',
            'slug' => 'toyota',
            'is_active' => true,
        ]);

        $model = VehicleModel::create([
            'vehicle_brand_id' => $brand->id,
            'name' => 'Corolla',
            'slug' => 'corolla',
            'is_active' => true,
        ]);

        $generation = VehicleGeneration::create([
            'vehicle_model_id' => $model->id,
            'name' => 'E210',
            'slug' => 'e210',
            'year_start' => 2019,
            'year_end' => 2023,
            'is_active' => true,
        ]);

        $trim = VehicleTrim::create([
            'vehicle_generation_id' => $generation->id,
            'name' => 'SE',
            'slug' => 'se',
            'is_active' => true,
        ]);

        $engine = VehicleEngine::create([
            'vehicle_trim_id' => $trim->id,
            'name' => '2.0L 4-Cylinder',
            'slug' => '2l-4cyl',
            'displacement' => 2.0,
            'fuel_type' => 'gasoline',
            'horsepower' => 169,
            'is_active' => true,
        ]);

        return compact('brand', 'model', 'generation', 'trim', 'engine');
    }

    // ── Brands ────────────────────────────────────────────────────

    public function test_active_brands_are_returned(): void
    {
        $this->createActiveHierarchy();

        $this->getJson('/api/vehicles/brands')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'toyota');
    }

    public function test_inactive_brands_are_excluded(): void
    {
        VehicleBrand::create([
            'name' => 'Honda',
            'slug' => 'honda',
            'is_active' => false,
        ]);

        $this->getJson('/api/vehicles/brands')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_brand_returns_404(): void
    {
        $brand = VehicleBrand::create([
            'name' => 'Honda',
            'slug' => 'honda',
            'is_active' => false,
        ]);

        $this->getJson("/api/vehicles/brands/{$brand->id}")
            ->assertNotFound();
    }

    public function test_brand_tree_returns_active_hierarchy(): void
    {
        $h = $this->createActiveHierarchy();

        $this->getJson("/api/vehicles/brands/{$h['brand']->id}")
            ->assertOk()
            ->assertJsonPath('slug', 'toyota')
            ->assertJsonCount(1, 'models')
            ->assertJsonPath('models.0.slug', 'corolla')
            ->assertJsonCount(1, 'models.0.generations')
            ->assertJsonPath('models.0.generations.0.slug', 'e210')
            ->assertJsonCount(1, 'models.0.generations.0.trims')
            ->assertJsonPath('models.0.generations.0.trims.0.slug', 'se')
            ->assertJsonCount(1, 'models.0.generations.0.trims.0.engines')
            ->assertJsonPath('models.0.generations.0.trims.0.engines.0.slug', '2l-4cyl');
    }

    public function test_brand_models_returns_active_models(): void
    {
        $h = $this->createActiveHierarchy();

        $this->getJson("/api/vehicles/brands/{$h['brand']->id}/models")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'corolla');
    }

    public function test_inactive_models_are_excluded(): void
    {
        $h = $this->createActiveHierarchy();

        $h['model']->update(['is_active' => false]);

        $this->getJson("/api/vehicles/brands/{$h['brand']->id}/models")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_brand_models_returns_404(): void
    {
        $brand = VehicleBrand::create([
            'name' => 'Honda',
            'slug' => 'honda',
            'is_active' => false,
        ]);

        $this->getJson("/api/vehicles/brands/{$brand->id}/models")
            ->assertNotFound();
    }

    // ── Generations ───────────────────────────────────────────────

    public function test_model_generations_returns_active_generations(): void
    {
        $h = $this->createActiveHierarchy();

        $this->getJson("/api/vehicles/models/{$h['model']->id}/generations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'e210');
    }

    public function test_inactive_generations_are_excluded(): void
    {
        $h = $this->createActiveHierarchy();

        $h['generation']->update(['is_active' => false]);

        $this->getJson("/api/vehicles/models/{$h['model']->id}/generations")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_model_generations_returns_404(): void
    {
        $model = VehicleModel::create([
            'vehicle_brand_id' => VehicleBrand::create(['name' => 'X', 'slug' => 'x'])->id,
            'name' => 'Y',
            'slug' => 'y',
            'is_active' => false,
        ]);

        $this->getJson("/api/vehicles/models/{$model->id}/generations")
            ->assertNotFound();
    }

    // ── Trims ─────────────────────────────────────────────────────

    public function test_generation_trims_returns_active_trims(): void
    {
        $h = $this->createActiveHierarchy();

        $this->getJson("/api/vehicles/generations/{$h['generation']->id}/trims")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'se');
    }

    public function test_inactive_trims_are_excluded(): void
    {
        $h = $this->createActiveHierarchy();

        $h['trim']->update(['is_active' => false]);

        $this->getJson("/api/vehicles/generations/{$h['generation']->id}/trims")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_generation_trims_returns_404(): void
    {
        $brand = VehicleBrand::create(['name' => 'X', 'slug' => 'x', 'is_active' => true]);
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => 'Y', 'slug' => 'y', 'is_active' => true]);
        $generation = VehicleGeneration::create(['vehicle_model_id' => $model->id, 'name' => 'Z', 'slug' => 'z', 'is_active' => false]);

        $this->getJson("/api/vehicles/generations/{$generation->id}/trims")
            ->assertNotFound();
    }

    // ── Engines ───────────────────────────────────────────────────

    public function test_trim_engines_returns_active_engines(): void
    {
        $h = $this->createActiveHierarchy();

        $this->getJson("/api/vehicles/trims/{$h['trim']->id}/engines")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', '2l-4cyl');
    }

    public function test_inactive_engines_are_excluded(): void
    {
        $h = $this->createActiveHierarchy();

        $h['engine']->update(['is_active' => false]);

        $this->getJson("/api/vehicles/trims/{$h['trim']->id}/engines")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_inactive_trim_engines_returns_404(): void
    {
        $brand = VehicleBrand::create(['name' => 'X', 'slug' => 'x', 'is_active' => true]);
        $model = VehicleModel::create(['vehicle_brand_id' => $brand->id, 'name' => 'Y', 'slug' => 'y', 'is_active' => true]);
        $generation = VehicleGeneration::create(['vehicle_model_id' => $model->id, 'name' => 'Z', 'slug' => 'z', 'is_active' => true]);
        $trim = VehicleTrim::create(['vehicle_generation_id' => $generation->id, 'name' => 'W', 'slug' => 'w', 'is_active' => false]);

        $this->getJson("/api/vehicles/trims/{$trim->id}/engines")
            ->assertNotFound();
    }

    public function test_engines_exclude_nonexistent_engines(): void
    {
        $this->getJson('/api/vehicles/trims/999999/engines')
            ->assertNotFound();
    }
}
