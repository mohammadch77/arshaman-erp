<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ستون‌ها دقیقاً طبق docs/schema_inventory_mysql.sql (جدول ۷). carrier عمداً
     * VARCHAR است نه ENUM (یادداشت ۱۰ همان سند) — شرکت حمل مقدار باز است.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('owner_company_id');
            $table->string('carrier', 30)->default('tipax');
            $table->string('tracking_code', 100)->nullable();
            $table->enum('status', ['pending', 'packed', 'shipped', 'delivered'])->default('pending');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('shipping_cost_amount', 18, 2)->default(0);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('order_id', 'idx_shipments_order');
            $table->index(['owner_company_id', 'status'], 'idx_shipments_company_status');

            $table->foreign('order_id', 'fk_shipments_order')->references('id')->on('orders');
            $table->foreign('owner_company_id', 'fk_shipments_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_shipments_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
