<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(UserSeeder::class);

        // The automotive/demo taxonomy seeders must never run against the
        // production (fashion) database: they use updateOrCreate + sync and would
        // inject automotive categories, attributes and products. Production only
        // needs the idempotent FashionVariantAxisSeeder.
        if (app()->environment('production')) {
            $this->command?->warn(
                'Production environment detected: skipping automotive/demo seeders. '
                . 'Only UserSeeder and FashionVariantAxisSeeder will run.'
            );

            $this->call(FashionVariantAxisSeeder::class);

            return;
        }

        $this->call([
            CategorySeeder::class,
            AttributeSeeder::class,
            AttributeValueSeeder::class,
            CategoryAttributeSeeder::class,
            FashionVariantAxisSeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
            ProductImageSeeder::class,
            VehicleReferenceSeeder::class,
            ProductVehicleCompatibilitySeeder::class,
        ]);
    }
}
