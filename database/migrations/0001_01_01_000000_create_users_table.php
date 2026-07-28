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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name', 200);
            $table->string('email', 200)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active', 'idx_users_active');
        });

        // self-referencing FKs added after table exists to avoid circular-creation error
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by_user_id', 'fk_users_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_users_updated_by')->references('id')->on('users');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
