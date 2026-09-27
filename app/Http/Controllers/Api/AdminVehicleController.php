<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminVehicleController extends Controller
{
    // ── Brand ──────────────────────────────────────────────────────

    public function indexBrands(): JsonResponse
    {
        $brands = VehicleBrand::query()
            ->withCount('models')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $brands]);
    }

    public function storeBrand(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vehicle_brands', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand = VehicleBrand::create($data);

        return response()->json($brand, 201);
    }

    public function showBrand(VehicleBrand $brand): JsonResponse
    {
        $brand->loadCount('models');

        return response()->json($brand);
    }

    public function updateBrand(Request $request, VehicleBrand $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('vehicle_brands', 'slug')->ignore($brand->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $brand->update($data);

        return response()->json($brand->fresh());
    }

    public function destroyBrand(VehicleBrand $brand): Response
    {
        if ($brand->models()->exists()) {
            throw ValidationException::withMessages([
                'brand' => ['Cannot delete brand while it has models.'],
            ]);
        }

        $brand->delete();

        return response()->noContent();
    }

    // ── Model ──────────────────────────────────────────────────────

    public function indexModels(VehicleBrand $brand): JsonResponse
    {
        $models = $brand->models()
            ->withCount('generations')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $models]);
    }

    public function storeModel(Request $request, VehicleBrand $brand): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vehicle_models', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model = $brand->models()->create($data);

        return response()->json($model, 201);
    }

    public function showModel(VehicleBrand $brand, VehicleModel $model): JsonResponse
    {
        $this->ensureModelBelongsToBrand($model, $brand);
        $model->loadCount('generations');

        return response()->json($model);
    }

    public function updateModel(Request $request, VehicleBrand $brand, VehicleModel $model): JsonResponse
    {
        $this->ensureModelBelongsToBrand($model, $brand);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('vehicle_models', 'slug')->ignore($model->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $model->update($data);

        return response()->json($model->fresh());
    }

    public function destroyModel(VehicleBrand $brand, VehicleModel $model): Response
    {
        $this->ensureModelBelongsToBrand($model, $brand);

        if ($model->generations()->exists()) {
            throw ValidationException::withMessages([
                'model' => ['Cannot delete model while it has generations.'],
            ]);
        }

        $model->delete();

        return response()->noContent();
    }

    // ── Generation ─────────────────────────────────────────────────

    public function indexGenerations(VehicleBrand $brand, VehicleModel $model): JsonResponse
    {
        $this->ensureModelBelongsToBrand($model, $brand);

        $generations = $model->generations()
            ->withCount('trims')
            ->orderByDesc('year_start')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $generations]);
    }

    public function storeGeneration(Request $request, VehicleBrand $brand, VehicleModel $model): JsonResponse
    {
        $this->ensureModelBelongsToBrand($model, $brand);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vehicle_generations', 'slug')],
            'year_start' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'year_end' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $generation = $model->generations()->create($data);

        return response()->json($generation, 201);
    }

    public function showGeneration(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $generation->loadCount('trims');

        return response()->json($generation);
    }

    public function updateGeneration(
        Request $request,
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('vehicle_generations', 'slug')->ignore($generation->id),
            ],
            'year_start' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'year_end' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $generation->update($data);

        return response()->json($generation->fresh());
    }

    public function destroyGeneration(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation
    ): Response {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);

        if ($generation->trims()->exists()) {
            throw ValidationException::withMessages([
                'generation' => ['Cannot delete generation while it has trims.'],
            ]);
        }

        $generation->delete();

        return response()->noContent();
    }

    // ── Trim ───────────────────────────────────────────────────────

    public function indexTrims(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);

        $trims = $generation->trims()
            ->withCount('engines')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $trims]);
    }

    public function storeTrim(
        Request $request,
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vehicle_trims', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trim = $generation->trims()->create($data);

        return response()->json($trim, 201);
    }

    public function showTrim(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);
        $trim->loadCount('engines');

        return response()->json($trim);
    }

    public function updateTrim(
        Request $request,
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('vehicle_trims', 'slug')->ignore($trim->id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $trim->update($data);

        return response()->json($trim->fresh());
    }

    public function destroyTrim(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim
    ): Response {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);

        if ($trim->engines()->exists()) {
            throw ValidationException::withMessages([
                'trim' => ['Cannot delete trim while it has engines.'],
            ]);
        }

        $trim->delete();

        return response()->noContent();
    }

    // ── Engine ─────────────────────────────────────────────────────

    public function indexEngines(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);

        $engines = $trim->engines()
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $engines]);
    }

    public function indexAllEngines(): JsonResponse
    {
        $engines = VehicleEngine::query()
            ->where('is_active', true)
            ->with([
                'trim:id,name',
                'trim.generation:id,name',
                'trim.generation.model:id,name',
                'trim.generation.model.brand:id,name',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($engine) => [
                'id' => $engine->id,
                'name' => $engine->name,
                'slug' => $engine->slug,
                'trim' => $engine->trim?->name,
                'generation' => $engine->trim?->generation?->name,
                'model' => $engine->trim?->generation?->model?->name,
                'brand' => $engine->trim?->generation?->model?->brand?->name,
            ]);

        return response()->json(['data' => $engines]);
    }

    public function storeEngine(
        Request $request,
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('vehicle_engines', 'slug')],
            'displacement' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'horsepower' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $engine = $trim->engines()->create($data);

        return response()->json($engine, 201);
    }

    public function showEngine(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim,
        VehicleEngine $engine
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);
        $this->ensureEngineBelongsToTrim($engine, $trim);
        $engine->loadCount('products');

        return response()->json($engine);
    }

    public function updateEngine(
        Request $request,
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim,
        VehicleEngine $engine
    ): JsonResponse {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);
        $this->ensureEngineBelongsToTrim($engine, $trim);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('vehicle_engines', 'slug')->ignore($engine->id),
            ],
            'displacement' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'horsepower' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $engine->update($data);

        return response()->json($engine->fresh());
    }

    public function destroyEngine(
        VehicleBrand $brand,
        VehicleModel $model,
        VehicleGeneration $generation,
        VehicleTrim $trim,
        VehicleEngine $engine
    ): Response {
        $this->ensureModelBelongsToBrand($model, $brand);
        $this->ensureGenerationBelongsToModel($generation, $model);
        $this->ensureTrimBelongsToGeneration($trim, $generation);
        $this->ensureEngineBelongsToTrim($engine, $trim);

        if ($engine->products()->exists()) {
            throw ValidationException::withMessages([
                'engine' => ['Cannot delete engine while it has products.'],
            ]);
        }

        $engine->delete();

        return response()->noContent();
    }

    // ── Product ↔ Vehicle Compatibility ────────────────────────────

    public function indexCompatibility(Product $product): JsonResponse
    {
        $engines = $product->vehicleEngines()
            ->with([
                'trim.generation.model.brand',
            ])
            ->orderBy('vehicle_engines.name')
            ->get();

        $grouped = $engines->mapToGroups(function (VehicleEngine $engine) {
            $brand = $engine->trim->generation->model->brand;

            return [$brand->name => [
                'brand' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                ],
                'models' => [],
            ]];
        });

        $hierarchy = $engines->map(function (VehicleEngine $engine) {
            return [
                'engine' => [
                    'id' => $engine->id,
                    'name' => $engine->name,
                    'slug' => $engine->slug,
                    'displacement' => $engine->displacement,
                    'fuel_type' => $engine->fuel_type,
                    'horsepower' => $engine->horsepower,
                ],
                'trim' => [
                    'id' => $engine->trim->id,
                    'name' => $engine->trim->name,
                    'slug' => $engine->trim->slug,
                ],
                'generation' => [
                    'id' => $engine->trim->generation->id,
                    'name' => $engine->trim->generation->name,
                    'slug' => $engine->trim->generation->slug,
                    'year_start' => $engine->trim->generation->year_start,
                    'year_end' => $engine->trim->generation->year_end,
                ],
                'model' => [
                    'id' => $engine->trim->generation->model->id,
                    'name' => $engine->trim->generation->model->name,
                    'slug' => $engine->trim->generation->model->slug,
                ],
                'brand' => [
                    'id' => $engine->trim->generation->model->brand->id,
                    'name' => $engine->trim->generation->model->brand->name,
                    'slug' => $engine->trim->generation->model->brand->slug,
                ],
            ];
        })->values();

        return response()->json(['data' => $hierarchy]);
    }

    public function attachCompatibility(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'vehicle_engine_ids' => ['required', 'array', 'min:1'],
            'vehicle_engine_ids.*' => ['integer', 'exists:vehicle_engines,id'],
        ]);

        $existing = $product->vehicleEngines()
            ->whereIn('vehicle_engines.id', $data['vehicle_engine_ids'])
            ->pluck('vehicle_engines.id');

        $newIds = collect($data['vehicle_engine_ids'])
            ->diff($existing)
            ->values()
            ->all();

        if ($newIds !== []) {
            $product->vehicleEngines()->attach($newIds);
        }

        $count = $product->vehicleEngines()->count();

        return response()->json([
            'message' => 'Compatibility attached.',
            'total_compatible_engines' => $count,
        ]);
    }

    public function detachCompatibility(Product $product, VehicleEngine $engine): Response
    {
        $product->vehicleEngines()->detach($engine->id);

        return response()->noContent();
    }

    // ── Helpers ────────────────────────────────────────────────────

    private function ensureModelBelongsToBrand(VehicleModel $model, VehicleBrand $brand): void
    {
        if ($model->vehicle_brand_id !== $brand->id) {
            abort(404);
        }
    }

    private function ensureGenerationBelongsToModel(VehicleGeneration $generation, VehicleModel $model): void
    {
        if ($generation->vehicle_model_id !== $model->id) {
            abort(404);
        }
    }

    private function ensureTrimBelongsToGeneration(VehicleTrim $trim, VehicleGeneration $generation): void
    {
        if ($trim->vehicle_generation_id !== $generation->id) {
            abort(404);
        }
    }

    private function ensureEngineBelongsToTrim(VehicleEngine $engine, VehicleTrim $trim): void
    {
        if ($engine->vehicle_trim_id !== $trim->id) {
            abort(404);
        }
    }
}
