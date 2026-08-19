<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * بخش ۲ بازطراحی (داده) — هر آیتم JSON process_definitions.request_form_fields
     * (formable_type=process_definition) و process_steps.step_form_fields
     * (formable_type=process_step) به یک ردیف واقعی process_form_fields تبدیل
     * می‌شود؛ کلید (key) موجود عیناً حفظ می‌شود (نه تولید مجدد) چون هم در
     * condition_field احتمالی هم در request_data/step_data به همین کلید ارجاع
     * داده شده و باید در مهاجرت‌های بعدی (بخش ۳/۴) قابل‌ردیابی بماند.
     *
     * دفاعی: قبل از dropColumn، تعداد آیتم JSON (قبل) با تعداد ردیف نوشته‌شده
     * (بعد) مقایسه می‌شود — عدم تطابق یعنی throw، ستون JSON دست‌نخورده می‌ماند.
     */
    public function up(): void
    {
        $expectedCount = 0;
        $writtenCount = 0;

        $now = now();

        foreach (DB::table('process_definitions')->whereNotNull('request_form_fields')->get(['id', 'request_form_fields']) as $definition) {
            $fields = json_decode($definition->request_form_fields, true) ?? [];
            $expectedCount += count($fields);
            $writtenCount += $this->insertFields('process_definition', $definition->id, $fields, $now);
        }

        foreach (DB::table('process_steps')->whereNotNull('step_form_fields')->get(['id', 'step_form_fields']) as $step) {
            $fields = json_decode($step->step_form_fields, true) ?? [];
            $expectedCount += count($fields);
            $writtenCount += $this->insertFields('process_step', $step->id, $fields, $now);
        }

        if ($expectedCount !== $writtenCount) {
            throw new RuntimeException("مهاجرت فیلدهای فرم فرایند ناقص بود: {$expectedCount} فیلد JSON در برابر {$writtenCount} ردیف نوشته‌شده — ستون‌های JSON عمداً حذف نشدند.");
        }

        // این CHECK قدیمی (بند ۱۴/۱۵ DATABASE_CONVENTIONS) به request_form_fields
        // ارجاع دارد — قبل از دراپ کردن ستون باید حذف شود. بعد از این بازطراحی
        // دیگر لازم نیست: تفکیک وصل‌به‌ماژول/آزاد از طریق ENUM شدن subject_type
        // (بند ۱۶) و وجود/عدم‌وجود ردیف در process_form_fields به‌جای این ستون
        // تضمین می‌شود. فقط mysql — هرگز روی sqlite ساخته نشده بود.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_definitions DROP CHECK chk_process_definitions_subject_or_form');
        }

        Schema::table('process_definitions', function (Blueprint $table) {
            $table->dropColumn('request_form_fields');
        });

        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropColumn('step_form_fields');
        });
    }

    public function down(): void
    {
        Schema::table('process_definitions', function (Blueprint $table) {
            $table->json('request_form_fields')->nullable()->after('subject_type');
        });

        Schema::table('process_steps', function (Blueprint $table) {
            $table->json('step_form_fields')->nullable()->after('condition_value');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE process_definitions ADD CONSTRAINT chk_process_definitions_subject_or_form CHECK (subject_type IS NULL OR request_form_fields IS NULL)');
        }

        foreach (['process_definition' => 'process_definitions', 'process_step' => 'process_steps'] as $formableType => $tableName) {
            $rows = DB::table('process_form_fields')
                ->where('formable_type', $formableType)
                ->orderBy('display_order')
                ->get();

            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row->formable_id][] = [
                    'key' => $row->field_key,
                    'label' => $row->label,
                    'type' => $row->field_type,
                    'options' => $row->options !== null ? json_decode($row->options, true) : null,
                ];
            }

            $column = $formableType === 'process_definition' ? 'request_form_fields' : 'step_form_fields';

            foreach ($grouped as $formableId => $fields) {
                DB::table($tableName)->where('id', $formableId)->update([$column => json_encode($fields, JSON_UNESCAPED_UNICODE)]);
            }
        }

        Schema::dropIfExists('process_form_fields');
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function insertFields(string $formableType, string $formableId, array $fields, $now): int
    {
        $written = 0;

        foreach (array_values($fields) as $order => $field) {
            $type = $field['type'] ?? 'text';

            DB::table('process_form_fields')->insert([
                'id' => (string) Str::orderedUuid(),
                'formable_type' => $formableType,
                'formable_id' => $formableId,
                'field_key' => $field['key'],
                'label' => $field['label'] ?? $field['key'],
                'field_type' => $type,
                // طبق رفتار قبلی FormFieldValidator::rules(): همه‌ی انواع الزامی
                // بودند به‌جز boolean (که فقط قاعده‌ی 'boolean' داشت، نه 'required').
                'is_required' => $type !== 'boolean',
                'options' => isset($field['options']) ? json_encode($field['options'], JSON_UNESCAPED_UNICODE) : null,
                'display_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $written++;
        }

        return $written;
    }
};
