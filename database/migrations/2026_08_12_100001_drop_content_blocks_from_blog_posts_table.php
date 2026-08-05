<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ادیتور Editor.js با Quill جایگزین شد (رندر WYSIWYG معمولی، نه بلوکی) —
        // content_html تنها منبع محتوا شد، این ستون JSON دیگر مصرفی ندارد.
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('content_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->json('content_blocks')->nullable();
        });
    }
};
