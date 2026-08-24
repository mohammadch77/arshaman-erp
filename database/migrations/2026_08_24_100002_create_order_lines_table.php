<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دقیقاً طبق docs/schema_inventory_mysql.sql (جدول ۶) — Snapshot سه‌گانه
     * قابل‌مذاکره نیست: unit_sale_price_amount/unit_cost_amount/fulfillment_type
     * همیشه کپی لحظه فروش‌اند، هرگز reference زنده به products. عمداً بدون
     * owner_company_id (شرکت از طریق orders.owner_company_id مشخص است) و
     * بدون هیچ ستون timestamp/audit — دقیقاً همان ستون‌های سند طراحی، تأیید
     * صریح کارفرما قبل از این Session.
     */
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_sale_price_amount', 18, 2);
            $table->decimal('unit_cost_amount', 18, 2)->nullable();
            $table->enum('fulfillment_type', ['physical', 'digital', 'service']);
            $table->decimal('line_total_amount', 18, 2);

            $table->index('order_id', 'idx_order_lines_order');
            $table->index('product_id', 'idx_order_lines_product');

            $table->foreign('order_id', 'fk_order_lines_order')->references('id')->on('orders');
            $table->foreign('product_id', 'fk_order_lines_product')->references('id')->on('products');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE order_lines ADD CONSTRAINT chk_order_lines_qty_positive CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
