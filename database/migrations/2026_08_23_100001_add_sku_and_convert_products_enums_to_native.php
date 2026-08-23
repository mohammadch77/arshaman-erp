<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * همان دلیل مستندشده در docs/DATABASE_CONVENTIONS.md بند ۱۶ /
     * migration مرجع 2026_08_19_100001: بازسازی جدول روی SQLite نیاز به
     * rename موقت دارد که نباید داخل یک تراکنش دور آن انجام شود.
     */
    public $withinTransaction = false;

    /**
     * طبق قرارداد تازه‌ی ماژول‌های جدید (docs/DATABASE_CONVENTIONS.md بند ۱۴):
     * fulfillment_type/unit_of_measure از VARCHAR+CHECK به ENUM نیتیو MySQL
     * تبدیل می‌شوند. sku هم اضافه می‌شود (nullable، یکتا در سطح شرکت).
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 50)->nullable()->after('name');
        });

        DB::statement('ALTER TABLE products DROP CHECK chk_products_fulfillment_type');
        DB::statement("ALTER TABLE products MODIFY COLUMN fulfillment_type ENUM('physical', 'digital', 'service') NOT NULL DEFAULT 'physical'");
        DB::statement("ALTER TABLE products ADD COLUMN unit_of_measure ENUM('piece', 'kilogram', 'liter', 'meter', 'box') NOT NULL DEFAULT 'piece' AFTER fulfillment_type");

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['owner_company_id', 'sku'], 'uq_products_company_sku');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildSqlite(reverse: true);

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('uq_products_company_sku');
            $table->dropColumn(['sku', 'unit_of_measure']);
        });

        DB::statement("ALTER TABLE products MODIFY COLUMN fulfillment_type VARCHAR(20) NOT NULL DEFAULT 'physical'");
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_fulfillment_type CHECK (fulfillment_type IN ('physical', 'digital', 'service'))");
    }

    private function rebuildSqlite(bool $reverse = false): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('products', 'products_old');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::statement('DROP INDEX IF EXISTS uq_products_company_sku');
        DB::statement('DROP INDEX IF EXISTS idx_products_company');
        DB::statement('DROP INDEX IF EXISTS idx_products_fulfillment');
        DB::statement('DROP INDEX IF EXISTS idx_products_category');

        Schema::create('products', function (Blueprint $table) use ($reverse) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 150);

            if (! $reverse) {
                $table->string('sku', 50)->nullable();
            }

            $table->decimal('sale_price', 18, 2);
            $table->decimal('cost_price', 18, 2)->nullable();
            $table->integer('reorder_point')->nullable();
            $table->uuid('currency_id')->nullable();

            if ($reverse) {
                $table->string('fulfillment_type', 20)->default('physical');
            } else {
                $table->enum('fulfillment_type', ['physical', 'digital', 'service'])->default('physical');
                $table->enum('unit_of_measure', ['piece', 'kilogram', 'liter', 'meter', 'box'])->default('piece');
            }

            $table->string('woocommerce_product_id', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_products_company');
            $table->index(['owner_company_id', 'fulfillment_type'], 'idx_products_fulfillment');
            $table->index('category_id', 'idx_products_category');

            if (! $reverse) {
                $table->unique(['owner_company_id', 'sku'], 'uq_products_company_sku');
            }

            $table->foreign('owner_company_id', 'fk_products_company')->references('id')->on('companies');
            $table->foreign('category_id', 'fk_products_category')->references('id')->on('product_categories');
            $table->foreign('currency_id', 'fk_products_currency')->references('id')->on('currencies');
            $table->foreign('created_by_user_id', 'fk_products_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_products_updated_by')->references('id')->on('users');
        });

        // ستون‌های مشترک بین جدول قدیم و جدید — sku/unit_of_measure فقط در یک
        // طرف وجود دارند (بسته به جهت migration)، پس نباید در SELECT از طرف
        // دیگر خوانده شوند؛ مقدار پیش‌فرض ستون تازه خودش پر می‌شود.
        $commonColumns = 'id, owner_company_id, category_id, name, sale_price, cost_price, reorder_point, currency_id, fulfillment_type, woocommerce_product_id, is_active, created_by_user_id, updated_by_user_id, created_at, updated_at, deleted_at';

        DB::statement("INSERT INTO products ({$commonColumns}) SELECT {$commonColumns} FROM products_old");

        Schema::drop('products_old');
    }
};
