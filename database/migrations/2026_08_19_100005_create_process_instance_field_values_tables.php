<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بخش ۴ بازطراحی — جایگزین واقعی process_instances.request_data و
     * process_instance_logs.step_data (هر دو JSON) با دو جدول مقدار واقعی،
     * هرکدام FK واقعی به process_form_fields.
     */
    public function up(): void
    {
        Schema::create('process_instance_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('process_instance_id');
            $table->uuid('process_form_field_id');
            $table->text('value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['process_instance_id', 'process_form_field_id'], 'uq_pifv_instance_field');

            $table->foreign('process_instance_id', 'fk_pifv_instance')->references('id')->on('process_instances');
            $table->foreign('process_form_field_id', 'fk_pifv_field')->references('id')->on('process_form_fields');
        });

        Schema::create('process_instance_log_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('process_instance_log_id');
            $table->uuid('process_form_field_id');
            $table->text('value')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['process_instance_log_id', 'process_form_field_id'], 'uq_pilfv_log_field');

            $table->foreign('process_instance_log_id', 'fk_pilfv_log')->references('id')->on('process_instance_logs');
            $table->foreign('process_form_field_id', 'fk_pilfv_field')->references('id')->on('process_form_fields');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_instance_log_field_values');
        Schema::dropIfExists('process_instance_field_values');
    }
};
