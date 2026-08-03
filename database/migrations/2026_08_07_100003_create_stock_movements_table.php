<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // snapshot از stocks.owner_company_id در لحظه ثبت — تا ایزولاسیون شرکتی
            // مستقیم روی همین جدول هم بدون join به stocks اعمال شود (BelongsToCompany).
            $table->uuid('owner_company_id');
            $table->uuid('stock_id');
            // enum PHP در App\Modules\Inventory\Enums\MovementType + CHECK زیر.
            $table->string('movement_type', 10);
            // همیشه مثبت؛ جهت (افزایش/کاهش) از movement_type مشخص می‌شود.
            $table->unsignedInteger('quantity');
            // برای اتصال به سفارش در فاز بعد — فعلاً فقط ستون، بدون FK (طبق الگوی leads.source_order_id).
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

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های قبلی.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stock_movements ADD CONSTRAINT chk_stock_movements_type CHECK (movement_type IN ('in', 'out', 'adjust'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
