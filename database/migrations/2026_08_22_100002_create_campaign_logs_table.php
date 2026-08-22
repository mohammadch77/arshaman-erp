<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('contact_site_profile_id');
            $table->string('channel', 10);
            $table->string('status', 20)->default('simulated'); // در این فاز همیشه simulated
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->index('campaign_id', 'idx_campaign_logs_campaign');
            $table->index('contact_site_profile_id', 'idx_campaign_logs_profile');

            $table->foreign('campaign_id', 'fk_campaign_logs_campaign')->references('id')->on('campaigns');
            $table->foreign('contact_site_profile_id', 'fk_campaign_logs_profile')->references('id')->on('contact_site_profiles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
    }
};
