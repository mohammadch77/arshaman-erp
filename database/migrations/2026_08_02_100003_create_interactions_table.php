<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_site_profile_id');
            $table->uuid('owner_company_id');
            $table->string('interaction_type', 20); // call | telegram | site_form | purchase
            $table->text('notes')->nullable();
            $table->uuid('source_order_id')->nullable(); // TODO: FK به orders وقتی فاز ۳ ساخته شد
            $table->timestamp('occurred_at');
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['contact_site_profile_id', 'occurred_at'], 'idx_interactions_profile_date');
            $table->index('owner_company_id', 'idx_interactions_company');

            $table->foreign('contact_site_profile_id', 'fk_interactions_profile')->references('id')->on('contact_site_profiles');
            $table->foreign('owner_company_id', 'fk_interactions_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_interactions_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
