<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 200);
            $table->string('party_type', 20)->default('individual'); // individual | company
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_supplier')->default(false);
            $table->string('phone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('economic_code', 30)->nullable();
            $table->text('address')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_parties_company');
            $table->index(['owner_company_id', 'is_customer'], 'idx_parties_customer');
            $table->index(['owner_company_id', 'is_supplier'], 'idx_parties_supplier');

            $table->foreign('owner_company_id', 'fk_parties_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_parties_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_parties_updated_by')->references('id')->on('users');
        });

        // حداقل یکی از is_customer/is_supplier باید true باشد — Blueprint معادل ندارد، raw لازم است.
        // SQLite (محیط تست) ALTER TABLE ... ADD CONSTRAINT را پشتیبانی نمی‌کند؛ آنجا فقط نگهبان
        // سطح مدل (Party::booted) این قاعده را اجرا می‌کند. در MySQL واقعی هر دو لایه فعال‌اند.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE parties ADD CONSTRAINT chk_parties_role CHECK (is_customer = 1 OR is_supplier = 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
