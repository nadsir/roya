<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\VehicleEngine;
use Illuminate\Database\Seeder;

class ProductVehicleCompatibilitySeeder extends Seeder
{
    public function run(): void
    {
        // This association is copied from the local catalog, not independently
        // verified fitment information. See database/SEEDER_AUDIT.md.
        $product = Product::where('slug', 'air-filter-peugeot-pars')->firstOrFail();
        $engine = VehicleEngine::where('slug', 'سیبل')->firstOrFail();

        $product->vehicleEngines()->syncWithoutDetaching([$engine->id]);
    }
}
