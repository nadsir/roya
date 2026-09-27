<?php

namespace Database\Seeders;

use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use App\Models\VehicleGeneration;
use App\Models\VehicleModel;
use App\Models\VehicleTrim;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleReferenceSeeder extends Seeder
{
    public function run(): void
    {
        // Preserve the local reference data, including its unverified technical
        // values. See database/SEEDER_AUDIT.md before deploying this catalog.
        DB::transaction(function (): void {
            $brand = VehicleBrand::firstOrCreate(
                ['slug' => 'بنز'],
                ['name' => 'بنز', 'is_active' => true]
            );

            $model = VehicleModel::firstOrCreate(
                ['slug' => 'می باخ'],
                ['vehicle_brand_id' => $brand->id, 'name' => 'می باخ', 'is_active' => true]
            );

            $generation = VehicleGeneration::firstOrCreate(
                ['slug' => 'نسل 4'],
                [
                    'vehicle_model_id' => $model->id,
                    'name' => '4',
                    'year_start' => 1990,
                    'year_end' => 1991,
                    'is_active' => true,
                ]
            );

            $trim = VehicleTrim::firstOrCreate(
                ['slug' => 'تیپ3'],
                ['vehicle_generation_id' => $generation->id, 'name' => 'تیپ3', 'is_active' => true]
            );

            VehicleEngine::firstOrCreate(
                ['slug' => 'سیبل'],
                [
                    'vehicle_trim_id' => $trim->id,
                    'name' => 'سیل',
                    'displacement' => '100.0',
                    'fuel_type' => 'للب',
                    'horsepower' => 1000,
                    'is_active' => true,
                ]
            );
        });
    }
}
