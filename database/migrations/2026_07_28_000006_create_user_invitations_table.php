<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 200);
            $table->string('full_name', 200);
            $table->string('token', 64);
            $table->uuid('owner_company_id')->nullable();
            $table->uuid('assigned_role_id')->nullable();
            $table->uuid('invited_by_user_id');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();

            $table->unique('token', 'uq_invitation_token');
            $table->index('email', 'idx_invitations_email');

            $table->foreign('owner_company_id', 'fk_inv_company')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('assigned_role_id', 'fk_inv_role')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('invited_by_user_id', 'fk_inv_invited_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
