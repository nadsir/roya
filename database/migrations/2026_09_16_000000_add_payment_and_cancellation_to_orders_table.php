<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('notes');
            $table->string('payment_method', 32)->nullable()->after('paid_at');
            $table->string('payment_ref', 255)->nullable()->after('payment_method');
            $table->timestamp('cancelled_at')->nullable()->after('payment_ref');
            $table->text('cancelled_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'paid_at', 'payment_method', 'payment_ref',
                'cancelled_at', 'cancelled_reason',
            ]);
        });
    }
};
