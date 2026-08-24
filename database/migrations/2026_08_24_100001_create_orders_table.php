<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ستون‌ها دقیقاً طبق docs/schema_inventory_mysql.sql (جدول ۵). order_number
     * ترتیبی و به‌ازای هر شرکت است، هرگز از ورودی کاربر — Action با قفل ردیف
     * companies آن را تولید می‌کند (بند ۷ یادداشت پایان همان سند).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->unsignedBigInteger('order_number');
            $table->uuid('party_id');
            $table->enum('order_status', [
                'received',
                'paid',
                'preparing',
                'shipped',
                'delivered',
                'delivered_instant',
                'closed',
                'cancelled',
                'returned',
            ])->default('received');
            $table->enum('source', [
                'woocommerce',
                'manual_instagram',
                'manual_telegram',
                'manual_other',
            ]);
            $table->string('external_order_id', 100)->nullable();
            $table->decimal('exchange_rate_snapshot', 18, 2)->nullable();
            $table->uuid('currency_id')->nullable();
            $table->decimal('subtotal_amount', 18, 2)->default(0);
            $table->decimal('shipping_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_company_id', 'order_number'], 'uq_orders_company_number');
            $table->unique(['owner_company_id', 'source', 'external_order_id'], 'uq_orders_company_source_external');
            $table->index(['owner_company_id', 'order_status'], 'idx_orders_company_status');
            $table->index('party_id', 'idx_orders_party');

            $table->foreign('owner_company_id', 'fk_orders_company')->references('id')->on('companies');
            $table->foreign('party_id', 'fk_orders_party')->references('id')->on('parties');
            $table->foreign('currency_id', 'fk_orders_currency')->references('id')->on('currencies');
            $table->foreign('created_by_user_id', 'fk_orders_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_orders_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
