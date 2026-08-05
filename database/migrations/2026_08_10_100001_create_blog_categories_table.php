<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->string('description', 255)->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_blog_categories_company');
            $table->unique(['owner_company_id', 'slug'], 'uq_blog_categories_company_slug');

            $table->foreign('owner_company_id', 'fk_blog_categories_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_blog_categories_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_blog_categories_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
