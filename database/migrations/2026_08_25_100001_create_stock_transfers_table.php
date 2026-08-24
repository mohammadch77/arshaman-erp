<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جابجایی موجودی بین دو انبار — یک رکورد رویداد (مثل stock_movements)، نه
     * یک موجودیت قابل‌ویرایش: بدون updated_at/soft delete. هر جابجایی موفق دقیقاً
     * دو ردیف stock_movements (transfer_out روی مبدأ، transfer_in روی مقصد) با
     * همین stock_transfer_id می‌سازد — نگاه کن TransferStock Action.
     */
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('product_id');
            $table->uuid('from_warehouse_id');
            $table->uuid('to_warehouse_id');
            $table->decimal('quantity', 18, 4);
            $table->text('note')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['owner_company_id', 'product_id'], 'idx_stock_transfers_company_product');
            $table->index('from_warehouse_id', 'idx_stock_transfers_from_warehouse');
            $table->index('to_warehouse_id', 'idx_stock_transfers_to_warehouse');

            $table->foreign('owner_company_id', 'fk_stock_transfers_company')->references('id')->on('companies');
            $table->foreign('product_id', 'fk_stock_transfers_product')->references('id')->on('products');
            $table->foreign('from_warehouse_id', 'fk_stock_transfers_from_warehouse')->references('id')->on('warehouses');
            $table->foreign('to_warehouse_id', 'fk_stock_transfers_to_warehouse')->references('id')->on('warehouses');
            $table->foreign('created_by_user_id', 'fk_stock_transfers_created_by')->references('id')->on('users');
        });

        // دفاع دولایه (الگوی chk_stock_movements_qty_positive/chk_stocks_quantity_on_hand_non_negative):
        // چک سطح Action + CHECK دیتابیس — قاعده‌ای غیر از نوع ستون، پس حتی روی sqlite هم به‌صورت
        // دستی (نه از طریق $table->enum) باید guard غیر-sqlite بگیرد.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT chk_stock_transfers_qty_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT chk_stock_transfers_different_warehouses CHECK (from_warehouse_id <> to_warehouse_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
