<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->string('sku')->nullable()->unique();

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->decimal('price', 15, 2);

            $table->decimal('compare_at_price', 15, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamp('published_at')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'published_at']);
            $table->index(['is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};