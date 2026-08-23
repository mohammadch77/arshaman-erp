<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول قبلی (migration 2026_08_07_100003) هنوز هیچ ردیف واقعی روی
     * arshaman_erp نداشت (تأیید شده قبل از این Session با شمارش مستقیم) —
     * پس بازسازی کامل جدول (drop+create) به‌جای ALTER چندمرحله‌ای، امن‌ترین
     * و ساده‌ترین راه است؛ چیزی برای مهاجرت داده وجود ندارد. هیچ جدول دیگری
     * FK به stock_movements.id ندارد (تأیید شده با grep)، پس drop امن است.
     *
     * $table->enum() در Blueprint خودش هم روی mysql (ENUM نیتیو) هم روی
     * sqlite (VARCHAR+CHECK معادل) کامپایل می‌شود — نیازی به دستکاری دستی
     * PRAGMA/rebuild مثل ماژول Process نیست، چون آنجا یک ستون *موجود* روی
     * جدول پر از داده تغییر نوع می‌داد؛ اینجا از صفر ساخته می‌شود.
     */
    public function up(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // snapshot از stocks.owner_company_id در لحظه ثبت — تا ایزولاسیون شرکتی
            // مستقیم روی همین جدول هم بدون join به stocks اعمال شود (BelongsToCompany).
            $table->uuid('owner_company_id');
            $table->uuid('stock_id');
            $table->enum('movement_type', [
                'purchase_in',
                'sale_out',
                'return_in',
                'adjustment_in',
                'adjustment_out',
                'waste_out',
            ]);
            // همیشه مثبت؛ جهت (افزایش/کاهش) از movement_type مشخص می‌شود.
            $table->decimal('quantity', 18, 4);
            // فقط حرکت‌های ورودی خرید (unit_cost) برای محاسبه میانگین موزون پر می‌شود.
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->text('reference_note')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index('owner_company_id', 'idx_stock_movements_company');
            $table->index(['stock_id', 'occurred_at'], 'idx_stock_movements_stock_date');
            $table->index('movement_type', 'idx_stock_movements_type');

            $table->foreign('owner_company_id', 'fk_stock_movements_company')->references('id')->on('companies');
            $table->foreign('stock_id', 'fk_stock_movements_stock')->references('id')->on('stocks');
            $table->foreign('created_by_user_id', 'fk_stock_movements_created_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس در برابر مقدار غیرمثبت — قاعده‌ای غیر از
        // ENUM (که خودش نوع را تضمین می‌کند)، پس CHECK جدا همچنان لازم است.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_qty_positive CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('stock_id');
            $table->string('movement_type', 10);
            $table->unsignedInteger('quantity');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('reason', 200)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('owner_company_id', 'idx_stock_movements_company');
            $table->index('stock_id', 'idx_stock_movements_stock');

            $table->foreign('owner_company_id', 'fk_stock_movements_company')->references('id')->on('companies');
            $table->foreign('stock_id', 'fk_stock_movements_stock')->references('id')->on('stocks');
            $table->foreign('created_by_user_id', 'fk_stock_movements_created_by')->references('id')->on('users');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_type CHECK (movement_type IN ('in', 'out', 'adjust'))");
        }
    }
};
