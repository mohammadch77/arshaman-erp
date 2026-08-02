<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->uuid('contact_site_profile_id')->nullable(); // سرنخ می‌تواند بدون مخاطب کامل باشد
            $table->string('source', 30); // instagram | website | telegram | referral | other
            $table->string('pipeline_stage', 20)->default('new');
            $table->uuid('assigned_to_user_id')->nullable();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('contract_id')->nullable(); // TODO: اتصال قرارداد در فاز ۵ (فقط آرشامان) — نگاه کن BACKLOG.md
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['owner_company_id', 'pipeline_stage'], 'idx_leads_company_stage');
            $table->index('assigned_to_user_id', 'idx_leads_assigned');

            $table->foreign('owner_company_id', 'fk_leads_company')->references('id')->on('companies');
            $table->foreign('contact_site_profile_id', 'fk_leads_profile')->references('id')->on('contact_site_profiles');
            $table->foreign('assigned_to_user_id', 'fk_leads_assigned_to')->references('id')->on('users');
            $table->foreign('created_by_user_id', 'fk_leads_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_leads_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
