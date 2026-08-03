<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // طبق بند ۵.۸ CLAUDE.md: موجودی محصول X در انبار Y متعلق به شرکت Z، جدا
            // از موجودی همان محصول در همان انبار متعلق به شرکت دیگر — BelongsToCompany
            // اینجا، نه روی warehouses.
            $table->uuid('owner_company_id');
            $table->uuid('product_id');
            $table->uuid('warehouse_id');
            // تنها منبع درستی quantity، مجموع stock_movements است — هرگز مستقیم
            // update نشود؛ فقط از طریق ReceiveStock/IssueStock.
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id', 'owner_company_id'], 'uq_stocks_product_warehouse_company');
            $table->index('owner_company_id', 'idx_stocks_company');

            $table->foreign('owner_company_id', 'fk_stocks_company')->references('id')->on('companies');
            $table->foreign('product_id', 'fk_stocks_product')->references('id')->on('products');
            $table->foreign('warehouse_id', 'fk_stocks_warehouse')->references('id')->on('warehouses');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
