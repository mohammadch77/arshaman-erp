<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 40);
            $table->string('slug', 60);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_company_id', 'idx_blog_tags_company');
            $table->unique(['owner_company_id', 'slug'], 'uq_blog_tags_company_slug');

            $table->foreign('owner_company_id', 'fk_blog_tags_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_blog_tags_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_blog_tags_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tags');
    }
};
