<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_company_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('owner_company_id');
            $table->uuid('assigned_role_id');
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'owner_company_id'], 'uq_user_company');
            $table->index('user_id', 'idx_ucr_user');
            $table->index('owner_company_id', 'idx_ucr_company');

            $table->foreign('user_id', 'fk_ucr_user')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('owner_company_id', 'fk_ucr_company')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('assigned_role_id', 'fk_ucr_role')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_ucr_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_company_roles');
    }
};
