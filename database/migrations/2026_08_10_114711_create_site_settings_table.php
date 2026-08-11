<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id')->unique();
            $table->string('site_title', 100)->nullable();
            $table->string('site_tagline', 160)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('favicon_path', 255)->nullable();
            $table->uuid('homepage_page_id')->nullable();
            $table->uuid('blog_page_id')->nullable();
            $table->uuid('active_header_demo_id')->nullable();
            $table->uuid('active_footer_demo_id')->nullable();
            $table->timestamps();

            $table->foreign('owner_company_id', 'fk_site_settings_company')->references('id')->on('companies');
            $table->foreign('homepage_page_id', 'fk_site_settings_homepage')->references('id')->on('pages');
            $table->foreign('blog_page_id', 'fk_site_settings_blog_page')->references('id')->on('pages');
            $table->foreign('active_header_demo_id', 'fk_site_settings_header_demo')->references('id')->on('layout_demos');
            $table->foreign('active_footer_demo_id', 'fk_site_settings_footer_demo')->references('id')->on('layout_demos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
