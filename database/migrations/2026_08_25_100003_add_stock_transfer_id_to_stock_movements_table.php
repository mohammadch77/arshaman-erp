<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فقط برای دو رکورد transfer_out/transfer_in که TransferStock می‌سازد پر
     * می‌شود؛ برای بقیه انواع حرکت (خرید/فروش/تعدیل/مرجوعی/ضایعات) همیشه NULL
     * می‌ماند — قرارداد سطح Action، نه یک قید CHECK قابل بیان ساده روی enum.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('stock_transfer_id')->nullable()->after('stock_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('stock_transfer_id', 'fk_stock_movements_transfer')
                ->references('id')->on('stock_transfers');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign('fk_stock_movements_transfer');
            $table->dropColumn('stock_transfer_id');
        });
    }
};
