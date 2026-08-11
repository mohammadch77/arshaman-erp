<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ENUM نیتیو MySQL طبق استثنای مستند بخش ۱۴ DATABASE_CONVENTIONS.md
        // (تصمیم صریح کارفرما، مخصوص همین ستون). سراسری، بدون owner_company_id.
        Schema::create('page_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('category_key', ['home', 'about', 'contact', 'services', 'blog', 'login'])->unique();
            $table->string('name', 60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_categories');
    }
};
