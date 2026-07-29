<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // ترتیب مهم است: ابتدا اندیس یکتای جدید ساخته می‌شود تا FK همیشه
            // یک اندیس پشتیبان داشته باشد، بعد اندیس قدیمی حذف می‌شود
            // (MySQL اجازه نمی‌دهد اندیسی که پشت یک FK است بدون جایگزین حذف شود).
            $table->unique('user_id', 'uq_employees_user_id');
            $table->dropIndex('idx_employees_user');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index('user_id', 'idx_employees_user');
            $table->dropUnique('uq_employees_user_id');
        });
    }
};
