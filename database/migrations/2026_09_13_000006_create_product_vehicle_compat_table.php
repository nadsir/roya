<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_vehicle_compat', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_engine_id')
                ->constrained('vehicle_engines')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(
                ['product_id', 'vehicle_engine_id'],
                'pvc_product_engine_unique'
            );

            $table->index('vehicle_engine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_vehicle_compat');
    }
};
