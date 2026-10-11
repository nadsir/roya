<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_gallery_new')->default(false);
            $table->timestamp('gallery_new_at')->nullable();
            $table->index(['is_gallery_new', 'gallery_new_at'], 'products_gallery_new_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_gallery_new_index');
            $table->dropColumn(['is_gallery_new', 'gallery_new_at']);
        });
    }
};
