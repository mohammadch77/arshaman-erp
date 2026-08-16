<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_step_id');
            $table->uuid('to_step_id');
            $table->enum('on_result', ['approved', 'rejected', 'condition_true', 'condition_false']);

            $table->index('from_step_id', 'idx_process_transitions_from');
            $table->index('to_step_id', 'idx_process_transitions_to');

            $table->foreign('from_step_id', 'fk_process_transitions_from')->references('id')->on('process_steps');
            $table->foreign('to_step_id', 'fk_process_transitions_to')->references('id')->on('process_steps');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_transitions');
    }
};
