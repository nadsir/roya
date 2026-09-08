<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->enum('type', [
                'select',
                'multiselect',
                'color',
                'number',
                'boolean',
                'text',
            ])->default('select');

            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_filterable', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};