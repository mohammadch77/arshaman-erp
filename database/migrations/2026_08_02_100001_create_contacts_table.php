<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // پروفایل واحد هلدینگی (Golden Record) — عمداً بدون owner_company_id.
            // بین‌شرکتی است، مثل holidays در HR؛ هرگز BelongsToCompany روی مدل این جدول نگذار.
            $table->string('full_name', 200);
            $table->string('phone', 20);
            $table->string('email', 200)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone', 'idx_contacts_phone');
            $table->index('email', 'idx_contacts_email');

            $table->foreign('created_by_user_id', 'fk_contacts_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_contacts_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
