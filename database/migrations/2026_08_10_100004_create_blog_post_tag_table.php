<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->uuid('post_id');
            $table->uuid('tag_id');

            $table->primary(['post_id', 'tag_id']);

            $table->foreign('post_id', 'fk_blog_post_tag_post')->references('id')->on('blog_posts')->cascadeOnDelete();
            $table->foreign('tag_id', 'fk_blog_post_tag_tag')->references('id')->on('blog_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
    }
};
