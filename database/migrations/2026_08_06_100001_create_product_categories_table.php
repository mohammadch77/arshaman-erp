<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_product_categories_company');

            $table->foreign('owner_company_id', 'fk_product_categories_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_product_categories_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_product_categories_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
