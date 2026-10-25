<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_next_chapter', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('eyebrow')->default('YOUR NEXT CHAPTER');
            $table->string('heading')->default('انتخاب بعدی، به سلیقه شما.');
            $table->text('intro')->nullable();

            $table->boolean('delivery_enabled')->default(true);
            $table->string('delivery_title')->default('استایل شما، در سراسر ایران');
            $table->text('delivery_description')->nullable();
            $table->string('delivery_image')->nullable();
            $table->string('delivery_cta_label')->default('مشاهده محصولات');
            $table->string('delivery_cta_url')->default('/store');

            $table->boolean('wholesale_enabled')->default(true);
            $table->string('wholesale_title')->default('همکاری عمده با رویا گالری');
            $table->text('wholesale_description')->nullable();
            $table->string('wholesale_image')->nullable();
            $table->string('wholesale_cta_label')->default('درخواست همکاری عمده');
            $table->string('whatsapp_number')->default('09126612898');
            $table->text('whatsapp_message')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_next_chapter');
    }
};