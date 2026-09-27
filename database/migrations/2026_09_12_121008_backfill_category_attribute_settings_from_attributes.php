<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('category_attributes')
                ->join(
                    'attributes',
                    'category_attributes.attribute_id',
                    '=',
                    'attributes.id'
                )
                ->orderBy('category_attributes.id')
                ->select([
                    'category_attributes.id',
                    'attributes.is_filterable',
                    'attributes.is_required',
                ])
                ->each(function (object $categoryAttribute): void {
                    DB::table('category_attributes')
                        ->where('id', $categoryAttribute->id)
                        ->update([
                            'is_filterable' => $categoryAttribute->is_filterable,
                            'is_required' => $categoryAttribute->is_required,
                        ]);
                });

            return;
        }

        DB::table('category_attributes')
            ->join(
                'attributes',
                'category_attributes.attribute_id',
                '=',
                'attributes.id'
            )
            ->update([
                'category_attributes.is_filterable' => DB::raw('attributes.is_filterable'),
                'category_attributes.is_required' => DB::raw('attributes.is_required'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // این Migration فقط داده‌های قبلی را به Pivot منتقل می‌کند.
        // برای جلوگیری از حذف یا بازگردانی اشتباه تنظیمات،
        // در rollback هیچ تغییری روی داده‌ها اعمال نمی‌کنیم.
    }
};
