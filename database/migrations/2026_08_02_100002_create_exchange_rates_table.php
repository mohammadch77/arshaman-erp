<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('currency_id');
            $table->decimal('rate_to_toman', 18, 2); // هر ۱ واحد ارز = چند تومان
            $table->date('effective_date');
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['currency_id', 'effective_date'], 'uq_exchange_rate_currency_date');
            $table->index('effective_date', 'idx_exchange_rate_date');

            $table->foreign('currency_id', 'fk_exchange_rate_currency')->references('id')->on('currencies');
            $table->foreign('created_by_user_id', 'fk_exchange_rate_created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
