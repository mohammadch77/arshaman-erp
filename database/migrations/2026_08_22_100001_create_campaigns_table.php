<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_company_id');
            $table->string('name', 150);
            $table->string('trigger_type', 30); // winback_90days | shipping_notification | cross_sell | welcome_first_purchase
            $table->string('channel', 10); // telegram | sms
            $table->text('message_template');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->index('owner_company_id', 'idx_campaigns_company');

            $table->foreign('owner_company_id', 'fk_campaigns_company')->references('id')->on('companies');
            $table->foreign('created_by_user_id', 'fk_campaigns_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
