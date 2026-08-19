<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * بخش ۴ بازطراحی (داده) — process_instances.request_data (کلید→مقدار)
     * به process_instance_field_values، process_instance_logs.step_data به
     * process_instance_log_field_values. کلید هر آیتم JSON با field_key همان
     * process_form_fields (formable_type=process_definition برای instance،
     * formable_type=process_step برای log) نگاشت می‌شود — چون بخش ۲ کلیدها را
     * عیناً حفظ کرد. طبق طراحی موجود (UpdateProcessDefinition فقط وقتی تعریف
     * صفر instance دارد ساختار را رونویسی می‌کند)، یک instance/log همیشه با
     * فیلدهای همان نسخه از تعریف/مرحله مطابق است — کلید یتیم انتظار نمی‌رود،
     * ولی اگر پیش بیاید throw می‌شود تا مهاجرت متوقف شود.
     */
    public function up(): void
    {
        $expectedCount = 0;
        $writtenCount = 0;
        $now = now();

        foreach (DB::table('process_instances')->whereNotNull('request_data')->get(['id', 'process_definition_id', 'request_data']) as $instance) {
            $data = json_decode($instance->request_data, true) ?? [];
            $expectedCount += count($data);
            $writtenCount += $this->insertValues(
                'process_instance_field_values',
                'process_instance_id',
                $instance->id,
                'process_definition',
                $instance->process_definition_id,
                $data,
                $now,
            );
        }

        foreach (DB::table('process_instance_logs')->whereNotNull('step_data')->get(['id', 'step_id', 'step_data']) as $log) {
            $data = json_decode($log->step_data, true) ?? [];
            $expectedCount += count($data);
            $writtenCount += $this->insertValues(
                'process_instance_log_field_values',
                'process_instance_log_id',
                $log->id,
                'process_step',
                $log->step_id,
                $data,
                $now,
            );
        }

        if ($expectedCount !== $writtenCount) {
            throw new RuntimeException("مهاجرت مقادیر فرم فرایند ناقص بود: {$expectedCount} کلید JSON در برابر {$writtenCount} ردیف نوشته‌شده — ستون‌های JSON عمداً حذف نشدند.");
        }

        Schema::table('process_instances', function (Blueprint $table) {
            $table->dropColumn('request_data');
        });

        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->dropColumn('step_data');
        });
    }

    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->json('request_data')->nullable()->after('subject_id');
        });

        Schema::table('process_instance_logs', function (Blueprint $table) {
            $table->json('step_data')->nullable()->after('comment');
        });

        $instanceRows = DB::table('process_instance_field_values')
            ->join('process_form_fields', 'process_form_fields.id', '=', 'process_instance_field_values.process_form_field_id')
            ->select('process_instance_field_values.process_instance_id', 'process_form_fields.field_key', 'process_instance_field_values.value')
            ->get();

        $grouped = [];
        foreach ($instanceRows as $row) {
            $grouped[$row->process_instance_id][$row->field_key] = $row->value;
        }

        foreach ($grouped as $instanceId => $data) {
            DB::table('process_instances')->where('id', $instanceId)->update(['request_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        }

        $logRows = DB::table('process_instance_log_field_values')
            ->join('process_form_fields', 'process_form_fields.id', '=', 'process_instance_log_field_values.process_form_field_id')
            ->select('process_instance_log_field_values.process_instance_log_id', 'process_form_fields.field_key', 'process_instance_log_field_values.value')
            ->get();

        $groupedLogs = [];
        foreach ($logRows as $row) {
            $groupedLogs[$row->process_instance_log_id][$row->field_key] = $row->value;
        }

        foreach ($groupedLogs as $logId => $data) {
            DB::table('process_instance_logs')->where('id', $logId)->update(['step_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function insertValues(
        string $table,
        string $parentColumn,
        string $parentId,
        string $formableType,
        string $formableId,
        array $data,
        $now,
    ): int {
        $written = 0;

        foreach ($data as $key => $value) {
            $formField = DB::table('process_form_fields')
                ->where('formable_type', $formableType)
                ->where('formable_id', $formableId)
                ->where('field_key', $key)
                ->first();

            if ($formField === null) {
                throw new RuntimeException("مهاجرت مقدار فرم ناقص بود: فیلد «{$key}» در process_form_fields ({$formableType}={$formableId}) یافت نشد.");
            }

            DB::table($table)->insert([
                'id' => (string) Str::orderedUuid(),
                $parentColumn => $parentId,
                'process_form_field_id' => $formField->id,
                'value' => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            $written++;
        }

        return $written;
    }
};
