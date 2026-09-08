<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('sku')->nullable()->unique();

            $table->decimal('price', 15, 2)->nullable();

            $table->decimal('compare_at_price', 15, 2)->nullable();

            $table->unsignedInteger('stock')->default(0);

            $table->boolean('is_active')->default(true);

            /*
             * شناسه یکتای ترکیب ویژگی‌های Variant
             * مثال:
             * 12-25
             * یعنی attribute value های 12 و 25
             */
            $table->string('combination_key', 255);

            $table->timestamps();

            $table->unique(
                ['product_id', 'combination_key'],
                'pv_product_combination_unique'
            );

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};