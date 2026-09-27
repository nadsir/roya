<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function indexBrands(): JsonResponse
    {
        $brands = VehicleBrand::query()
            ->where('is_active', true)
            ->withCount('models')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $brands]);
    }

    public function showBrand(VehicleBrand $brand): JsonResponse
    {
        if (! $brand->is_active) {
            abort(404);
        }

        $brand->load([
            'models' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'models.generations' => fn ($q) => $q->where('is_active', true)->orderByDesc('year_start')->orderBy('name'),
            'models.generations.trims' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'models.generations.trims.engines' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
        ]);

        return response()->json($brand);
    }

    public function indexModels(VehicleBrand $brand): JsonResponse
    {
        if (! $brand->is_active) {
            abort(404);
        }

        $models = $brand->models()
            ->where('vehicle_models.is_active', true)
            ->withCount('generations')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $models]);
    }

    public function indexGenerations(VehicleModel $model): JsonResponse
    {
        if (! $model->is_active) {
            abort(404);
        }

        $generations = $model->generations()
            ->where('vehicle_generations.is_active', true)
            ->withCount('trims')
            ->orderByDesc('year_start')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $generations]);
    }

    public function indexTrims(VehicleGeneration $generation): JsonResponse
    {
        if (! $generation->is_active) {
            abort(404);
        }

        $trims = $generation->trims()
            ->where('vehicle_trims.is_active', true)
            ->withCount('engines')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $trims]);
    }

    public function indexEngines(VehicleTrim $trim): JsonResponse
    {
        if (! $trim->is_active) {
            abort(404);
        }

        $engines = $trim->engines()
            ->where('vehicle_engines.is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $engines]);
    }
}
