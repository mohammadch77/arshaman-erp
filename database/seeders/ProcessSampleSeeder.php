<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Company;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Database\Seeder;

/**
 * یک process_definition کاملاً ساختگی (نه HR — فقط برای تست ساختار موتور
 * گردش‌کار در Session بعدی) با زنجیره‌ی start→approval→condition→(دو
 * مسیر)→end. فرایند آزاد است (subject_type=null)، پس یک فرم درخواست ساده
 * (request_form_fields، همان ساختار editable_fields ماژول SiteBuilder) دارد.
 */
class ProcessSampleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'arshaman')->firstOrFail();

        // withoutGlobalScopes: بدون آن، Global Scope خودکار owner_company (بر پایه
        // CompanyContext::id() که در seeder چون کاربری واردنشده null است) کوئری
        // انتخاب رکورد موجود را با شرط تناقض‌دار می‌کند و هر بار seed دوباره تکراری می‌سازد.
        $definition = ProcessDefinition::withoutGlobalScopes()->updateOrCreate(
            ['owner_company_id' => $company->id, 'process_key' => 'sample_free_request'],
            [
                'name' => 'درخواست نمونه (تستی)',
                'subject_type' => null,
                'is_active' => true,
                'created_by_user_id' => null,
            ]
        );

        // زنجیره‌ی قبلی همین تعریف (اگر از اجرای قبلی seed مانده) پاک می‌شود تا
        // re-seed رکورد تکراری نسازد — این جدول‌ها هیچ کلید یکتای طبیعی دیگری
        // غیر از step_key در سطح همین تعریف ندارند.
        ProcessTransition::whereIn('from_step_id', $definition->steps()->pluck('id'))->delete();
        $definition->steps()->delete();
        ProcessFormField::where('formable_type', ProcessFormField::FORMABLE_DEFINITION)
            ->where('formable_id', $definition->id)
            ->delete();

        ProcessFormField::create([
            'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
            'formable_id' => $definition->id,
            'field_key' => 'title',
            'label' => 'عنوان درخواست',
            'field_type' => 'text',
            'is_required' => true,
            'display_order' => 0,
        ]);

        $amountField = ProcessFormField::create([
            'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
            'formable_id' => $definition->id,
            'field_key' => 'amount',
            'label' => 'مبلغ درخواستی',
            'field_type' => 'text',
            'is_required' => true,
            'display_order' => 1,
        ]);

        $start = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'start',
            'name' => 'شروع',
            'step_type' => StepType::Start,
        ]);

        $approval = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'manager_approval',
            'name' => 'تأیید مدیر',
            'step_type' => StepType::Approval,
            'assignment_type' => AssignmentType::Role,
            'assigned_role' => 'holding_admin',
        ]);

        $condition = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'amount_check',
            'name' => 'بررسی مبلغ',
            'step_type' => StepType::Condition,
            'condition_field_id' => $amountField->id,
            'condition_operator' => ConditionOperator::GreaterThan,
            'condition_value' => '1000000',
        ]);

        $endApproved = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'end_approved',
            'name' => 'پایان — تأییدشده',
            'step_type' => StepType::End,
        ]);

        $endRejected = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'end_rejected',
            'name' => 'پایان — ردشده',
            'step_type' => StepType::End,
        ]);

        ProcessTransition::create([
            'from_step_id' => $start->id,
            'to_step_id' => $approval->id,
            'on_result' => TransitionResult::Approved,
        ]);

        ProcessTransition::create([
            'from_step_id' => $approval->id,
            'to_step_id' => $condition->id,
            'on_result' => TransitionResult::Approved,
        ]);

        ProcessTransition::create([
            'from_step_id' => $approval->id,
            'to_step_id' => $endRejected->id,
            'on_result' => TransitionResult::Rejected,
        ]);

        ProcessTransition::create([
            'from_step_id' => $condition->id,
            'to_step_id' => $endApproved->id,
            'on_result' => TransitionResult::ConditionTrue,
        ]);

        ProcessTransition::create([
            'from_step_id' => $condition->id,
            'to_step_id' => $endRejected->id,
            'on_result' => TransitionResult::ConditionFalse,
        ]);
    }
}
