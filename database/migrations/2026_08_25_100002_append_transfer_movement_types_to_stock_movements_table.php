<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * افزودن transfer_out/transfer_in به انتهای ENUM موجود movement_type —
     * طبق قانون append-only مستند در پایان docs/schema_inventory_mysql.sql
     * (یادداشت ۹): مقادیر جدید فقط به انتهای لیست، ترتیب/مقادیر قبلی هرگز
     * عوض نمی‌شوند.
     *
     * روی MySQL یک ALTER ... MODIFY COLUMN ساده کافی است (ستون دیگری تغییر
     * نمی‌کند). روی SQLite ($table->enum() به VARCHAR+CHECK ترجمه می‌شود و
     * ویرایش یک CHECK موجود مستقیم پشتیبانی نمی‌شود) از همان تکنیک
     * rename+rebuild ماژول Process استفاده می‌شود (نگاه کن
     * 2026_08_19_100001_convert_process_subject_type_to_enum.php) —
     * stock_movements نه فرزند جدول دیگری است نه هیچ جدولی FK به
     * stock_movements.id دارد، پس بازسازی امن است.
     */
    public $withinTransaction = false;

    private const OLD_TYPES = [
        'purchase_in', 'sale_out', 'return_in', 'adjustment_in', 'adjustment_out', 'waste_out',
    ];

    private const NEW_TYPES = [
        'purchase_in', 'sale_out', 'return_in', 'adjustment_in', 'adjustment_out', 'waste_out',
        'transfer_out', 'transfer_in',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuild(self::NEW_TYPES);

            return;
        }

        $values = implode("','", self::NEW_TYPES);
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM('{$values}') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuild(self::OLD_TYPES);

            return;
        }

        $values = implode("','", self::OLD_TYPES);
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN movement_type ENUM('{$values}') NOT NULL");
    }

    /**
     * @param  array<int, string>  $types
     */
    private function rebuild(array $types): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        DB::statement('PRAGMA legacy_alter_table = ON');
        Schema::rename('stock_movements', 'stock_movements_old');
        DB::statement('PRAGMA legacy_alter_table = OFF');
        DB::statement('PRAGMA foreign_keys = ON');

        DB::statement('DROP INDEX IF EXISTS idx_stock_movements_company');
        DB::statement('DROP INDEX IF EXISTS idx_stock_movements_stock_date');
        DB::statement('DROP INDEX IF EXISTS idx_stock_movements_type');

        Schema::create('stock_movements', function (Blueprint $table) use ($types) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('stock_id');
            $table->enum('movement_type', $types);
            $table->decimal('quantity', 18, 4);
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

        $columns = 'id, owner_company_id, stock_id, movement_type, quantity, unit_cost, reference_note, created_by_user_id, occurred_at, created_at';

        DB::statement("INSERT INTO stock_movements ({$columns}) SELECT {$columns} FROM stock_movements_old");

        // CHECK دستی chk_stock_movements_qty_positive هرگز روی sqlite ساخته نشده
        // (guard غیر-sqlite در migration اصلی)، پس اینجا هم چیزی برای بازسازی‌اش نیست.
        Schema::drop('stock_movements_old');
    }
};
