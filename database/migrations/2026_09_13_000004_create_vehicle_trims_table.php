<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_trims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_generation_id')
                ->constrained('vehicle_generations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['vehicle_generation_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_trims');
    }
};
