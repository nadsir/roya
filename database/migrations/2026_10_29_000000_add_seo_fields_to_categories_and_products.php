<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Technical SEO / GEO fields to the categories and products tables.
 *
 * - Categories: editorial landing-page content (H1, top description, styling
 *   guide, FAQ) plus standard meta fields.
 * - Products: meta fields plus GEO-relevant structured data (brand, fabric,
 *   care instructions, size chart, return policy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('meta_title', 70)->nullable()->after('description');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('canonical_url', 2048)->nullable()->after('meta_description');

            $table->string('h1_title')->nullable()->after('canonical_url');
            $table->text('top_description')->nullable()->after('h1_title');
            $table->longText('bottom_content')->nullable()->after('top_description');
            $table->json('faqs')->nullable()->after('bottom_content');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title', 70)->nullable()->after('description');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('canonical_url', 2048)->nullable()->after('meta_description');

            $table->string('brand')->default('گالری رویا')->after('canonical_url');
            $table->string('material')->nullable()->after('brand');
            $table->text('care_instructions')->nullable()->after('material');
            $table->json('size_chart')->nullable()->after('care_instructions');
        });

    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'canonical_url',
                'h1_title', 'top_description', 'bottom_content', 'faqs',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title', 'meta_description', 'canonical_url',
                'brand', 'material', 'care_instructions', 'size_chart',
            ]);
        });
    }
};
