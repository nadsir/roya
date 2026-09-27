<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('gateway', 64);
            $table->string('authority', 255)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('status', 32)->default('pending');
            $table->string('reference', 255)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('authority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
