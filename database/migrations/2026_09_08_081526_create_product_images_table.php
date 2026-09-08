<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
             * اگر تصویر مربوط به یک Variant خاص باشد،
             * مثلاً تصویر رنگ مشکی، این فیلد استفاده می‌شود.
             */
            $table->foreignId('variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->string('path');

            $table->string('alt_text')->nullable();

            $table->boolean('is_primary')->default(false);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['product_id', 'sort_order']);

            $table->index(['variant_id', 'sort_order']);

            $table->index(['product_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};