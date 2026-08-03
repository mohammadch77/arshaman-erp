<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 150);
            $table->decimal('sale_price', 18, 2);
            // nullable عمدی: بند ۵.۳ CLAUDE.md — تا وقتی بهای تمام‌شده مشخص نشده،
            // UI باید صریح هشدار «بهای تمام‌شده نامشخص» نشان دهد، نه اینکه صفر فرض شود.
            $table->decimal('cost_price', 18, 2)->nullable();
            // nullable: عدم وجود ردیف یعنی ارز پایه هلدینگ (تومان)، طبق الگوی
            // exchange_rates. فقط وقتی محصول با ارز خارجی قیمت‌گذاری شده پر می‌شود.
            $table->uuid('currency_id')->nullable();
            // enum PHP در App\Modules\Catalog\Enums\FulfillmentType + CHECK زیر —
            // در سطح محصول است نه شرکت، طبق بند ۵.۳ CLAUDE.md (Verifex هر دو نوع می‌فروشد).
            $table->string('fulfillment_type', 20)->default('physical');
            $table->string('woocommerce_product_id', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_products_company');
            $table->index(['owner_company_id', 'fulfillment_type'], 'idx_products_fulfillment');
            $table->index('category_id', 'idx_products_category');

            $table->foreign('owner_company_id', 'fk_products_company')->references('id')->on('companies');
            $table->foreign('category_id', 'fk_products_category')->references('id')->on('product_categories');
            $table->foreign('currency_id', 'fk_products_currency')->references('id')->on('currencies');
            $table->foreign('created_by_user_id', 'fk_products_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_products_updated_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های قبلی
        // (parties.party_type, leads.pipeline_stage, ...) — SQLite (محیط تست)
        // این سینتکس را پشتیبانی نمی‌کند، پس فقط روی mysql واقعی اعمال می‌شود.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_fulfillment_type CHECK (fulfillment_type IN ('physical', 'digital', 'service'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
