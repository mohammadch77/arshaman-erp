<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_site_profile_id');
            $table->uuid('owner_company_id');
            $table->string('subject', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open'); // open | in_progress | resolved | closed
            $table->string('priority', 10)->default('normal'); // low | normal | high
            $table->uuid('assigned_to_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_company_id', 'status'], 'idx_tickets_company_status');
            $table->index('contact_site_profile_id', 'idx_tickets_profile');

            $table->foreign('contact_site_profile_id', 'fk_tickets_profile')->references('id')->on('contact_site_profiles');
            $table->foreign('owner_company_id', 'fk_tickets_company')->references('id')->on('companies');
            $table->foreign('assigned_to_user_id', 'fk_tickets_assigned_to')->references('id')->on('users');
            $table->foreign('created_by_user_id', 'fk_tickets_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
