<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * طبق docs/PROJECT_04_INVENTORY.md (Session 2): quantity → quantity_on_hand
     * (decimal، چون unit_of_measure محصول می‌تواند کیلوگرم/لیتر باشد)، به‌علاوه
     * reorder_point اختصاصی هر ردیف موجودی (override اختیاری روی
     * products.reorder_point سطح شرکت — اگر خالی بود، همان مقدار محصول
     * مبنای هشدار می‌ماند؛ نگاه کن Stock::reorderThreshold()) و average_cost
     * برای ارزش‌گذاری میانگین موزون (Session بعد آن را واقعاً محاسبه می‌کند).
     */
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->renameColumn('quantity', 'quantity_on_hand');
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('quantity_on_hand', 18, 4)->default(0)->change();
            $table->decimal('reorder_point', 18, 4)->nullable()->after('quantity_on_hand');
            $table->decimal('average_cost', 18, 2)->nullable()->after('reorder_point');
        });

        // لایه دفاعی دوم سطح دیتابیس (لایه اول: Stock::booted در سطح مدل) —
        // دقیقاً الگوی chk_stock_movements_type/chk_products_fulfillment_type.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stocks ADD CONSTRAINT chk_stocks_quantity_on_hand_non_negative CHECK (quantity_on_hand >= 0)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stocks DROP CHECK chk_stocks_quantity_on_hand_non_negative');
        }

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['reorder_point', 'average_cost']);
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->integer('quantity_on_hand')->default(0)->change();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->renameColumn('quantity_on_hand', 'quantity');
        });
    }
};
