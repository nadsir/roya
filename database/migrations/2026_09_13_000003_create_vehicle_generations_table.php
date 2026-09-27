<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_generations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_model_id')
                ->constrained('vehicle_models')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->smallInteger('year_start')->nullable();
            $table->smallInteger('year_end')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['vehicle_model_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_generations');
    }
};
