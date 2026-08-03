<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfm_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_site_profile_id');
            $table->uuid('owner_company_id');
            $table->integer('recency_days')->nullable();
            $table->integer('frequency_count')->nullable();
            $table->decimal('monetary_amount', 18, 2)->nullable();
            $table->string('segment', 20)->default('new'); // vip | at_risk | dormant | new
            $table->timestamp('calculated_at')->nullable();

            $table->unique('contact_site_profile_id', 'uq_rfm_profile');
            $table->index(['owner_company_id', 'segment'], 'idx_rfm_company_segment');

            $table->foreign('contact_site_profile_id', 'fk_rfm_profile')->references('id')->on('contact_site_profiles');
            $table->foreign('owner_company_id', 'fk_rfm_company')->references('id')->on('companies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfm_segments');
    }
};
