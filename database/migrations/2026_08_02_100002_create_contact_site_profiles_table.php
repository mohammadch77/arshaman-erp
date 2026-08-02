<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_site_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('owner_company_id');
            $table->uuid('party_id')->nullable(); // لینک اختیاری به طرف‌حساب مالی — Party ≠ Contact
            $table->string('site_full_name', 200)->nullable(); // اگر نام محلی با نام هلدینگ فرق داشت
            $table->timestamp('first_seen_at')->nullable();
            $table->decimal('total_purchase_amount', 18, 2)->default(0); // فعلاً صفر تا سفارش واقعی داشته باشیم
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['contact_id', 'owner_company_id'], 'uq_contact_site_profile');
            $table->index('owner_company_id', 'idx_contact_site_profile_company');
            $table->index('party_id', 'idx_contact_site_profile_party');

            $table->foreign('contact_id', 'fk_csp_contact')->references('id')->on('contacts');
            $table->foreign('owner_company_id', 'fk_csp_company')->references('id')->on('companies');
            $table->foreign('party_id', 'fk_csp_party')->references('id')->on('parties');
            $table->foreign('created_by_user_id', 'fk_csp_created_by')->references('id')->on('users');
            $table->foreign('updated_by_user_id', 'fk_csp_updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_site_profiles');
    }
};
