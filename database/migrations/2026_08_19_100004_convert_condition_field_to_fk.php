<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * بخش ۳ بازطراحی — process_steps.condition_field (VARCHAR آزاد) با دو ستون
     * جایگزین می‌شود: condition_field_id (FK واقعی به process_form_fields، فقط
     * برای شرط روی فرم آزاد همان تعریف) و condition_module_field (VARCHAR،
     * whitelist‌شده در config/processes.php، برای شرط روی فیلد ماژول). دقیقاً
     * یکی پر است وقتی step_type='condition' — CHECK دستی guard غیر-sqlite،
     * الگوی دقیق chk_process_definitions_subject_or_form.
     */
    public function up(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->uuid('condition_field_id')->nullable()->after('condition_field');
            $table->string('condition_module_field', 60)->nullable()->after('condition_field_id');
        });

        Schema::table('process_steps', function (Blueprint $table) {
            $table->foreign('condition_field_id', 'fk_process_steps_condition_field')
                ->references('id')->on('process_form_fields');
        });

        $steps = DB::table('process_steps')
            ->join('process_definitions', 'process_definitions.id', '=', 'process_steps.process_definition_id')
            ->whereNotNull('process_steps.condition_field')
            ->select('process_steps.id', 'process_steps.condition_field', 'process_steps.process_definition_id', 'process_definitions.subject_type')
            ->get();

        foreach ($steps as $step) {
            if ($step->subject_type !== null) {
                DB::table('process_steps')->where('id', $step->id)->update([
                    'condition_module_field' => $step->condition_field,
                ]);

                continue;
            }

            $formField = DB::table('process_form_fields')
                ->where('formable_type', 'process_definition')
                ->where('formable_id', $step->process_definition_id)
                ->where('field_key', $step->condition_field)
                ->first();

            if ($formField === null) {
                throw new RuntimeException("مهاجرت condition_field ناقص بود: فیلد «{$step->condition_field}» برای مرحله {$step->id} در process_form_fields یافت نشد.");
            }

            DB::table('process_steps')->where('id', $step->id)->update([
                'condition_field_id' => $formField->id,
            ]);
        }

        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropColumn('condition_field');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_steps ADD CONSTRAINT chk_process_steps_condition_source CHECK (step_type <> \'condition\' OR ((condition_field_id IS NOT NULL) <> (condition_module_field IS NOT NULL)))');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_steps DROP CHECK chk_process_steps_condition_source');
        }

        Schema::table('process_steps', function (Blueprint $table) {
            $table->string('condition_field', 60)->nullable()->after('assigned_user_id');
        });

        $steps = DB::table('process_steps')
            ->where(function ($q) {
                $q->whereNotNull('condition_field_id')->orWhereNotNull('condition_module_field');
            })
            ->select('id', 'condition_field_id', 'condition_module_field')
            ->get();

        foreach ($steps as $step) {
            if ($step->condition_module_field !== null) {
                DB::table('process_steps')->where('id', $step->id)->update(['condition_field' => $step->condition_module_field]);

                continue;
            }

            $formField = DB::table('process_form_fields')->where('id', $step->condition_field_id)->first();

            DB::table('process_steps')->where('id', $step->id)->update([
                'condition_field' => $formField?->field_key,
            ]);
        }

        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropForeign('fk_process_steps_condition_field');
            $table->dropColumn(['condition_field_id', 'condition_module_field']);
        });
    }
};
