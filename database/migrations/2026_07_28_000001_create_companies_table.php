<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('slug', 50);
            $table->string('business_type', 30);
            $table->string('base_currency', 3)->default('IRR');
            $table->json('woocommerce_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('slug', 'uq_companies_slug');
            $table->index('is_active', 'idx_companies_active');
            $table->foreign('created_by_user_id', 'fk_companies_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_companies_updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
