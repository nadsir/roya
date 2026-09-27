<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_engines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vehicle_trim_id')
                ->constrained('vehicle_trims')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->decimal('displacement', 4, 1)->nullable();
            $table->string('fuel_type')->nullable();
            $table->unsignedSmallInteger('horsepower')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['vehicle_trim_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_engines');
    }
};
