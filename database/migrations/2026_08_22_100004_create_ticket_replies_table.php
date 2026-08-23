<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('user_id');
            $table->text('message');
            $table->timestamp('created_at')->nullable();

            $table->index('ticket_id', 'idx_ticket_replies_ticket');

            $table->foreign('ticket_id', 'fk_ticket_replies_ticket')->references('id')->on('tickets');
            $table->foreign('user_id', 'fk_ticket_replies_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_replies');
    }
};
