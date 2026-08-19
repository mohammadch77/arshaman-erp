<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Company;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use Illuminate\Database\Seeder;

/**
 * اولین process_definition واقعی متصل به یک ماژول دیگر (HR) — نه یک زنجیره‌ی
 * ساختگی مثل ProcessSampleSeeder. append-only: جدا از آن seeder، فایل جداگانه.
 *
 * زنجیره:
 * start → تأیید سرپرست/HR (accountant) → بررسی مدت (days_count <= 5)
 *   ├─ بله (۵ روز یا کمتر) → مستقیم end_approved
 *   └─ خیر (بیشتر از ۵ روز) → تأیید اضافه‌ی مدیر ارشد (holding_admin) → end_approved / end_rejected
 * رد در هر مرحله‌ی approval → end_rejected
 *
 * نقش‌های واگذارشده عمداً همان دو نقشی هستند که LeavePolicy::review() هم‌اکنون
 * مجاز می‌داند (holding_admin/accountant، نه operator) — چون نتیجه‌ی نهایی هر
 * approval را ProcessEngine با همان actor به ApproveLeave/RejectLeave واقعی
 * پاس می‌دهد و آن Action دوباره همان Gate را چک می‌کند؛ اگر assigned_role اینجا
 * operator بود، آن فراخوانی خودکار همیشه با AuthorizationException شکست
 * می‌خورد، هرچند ProcessEngine خودش اجازه‌ی تأیید مرحله را داده بود.
 *
 * چرا condition_operator این‌جا LessThanOrEqual است، نه GreaterThan روی مسیر
 * برعکس: ProcessEngine::resolveEndStatus() نتیجه‌ی نهایی instance را همیشه از
 * روی نوع transition (condition_true → approved، condition_false → rejected)
 * تعیین می‌کند، نه از روی این‌که کدام end step فیزیکی هدف است (طبق تصمیم
 * صریح Session ۲ ماژول Process). پس مسیر «مستقیم تأیید» باید حتماً روی نتیجه‌ی
 * condition_true سوار شود؛ اگر اینجا GreaterThan(۵) با هدف end_approved
 * می‌گذاشتیم، همان مسیر (۵ روز یا کمتر) به‌جای approved، rejected ثبت می‌شد —
 * این باگ واقعاً یک‌بار در توسعه‌ی این Session رخ داد و با تست یکپارچه کشف شد.
 */
class ProcessLeaveDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->withoutGlobalScopes()->where('slug', 'arshaman')->firstOrFail();

        $definition = ProcessDefinition::withoutGlobalScopes()->updateOrCreate(
            ['owner_company_id' => $company->id, 'process_key' => 'hr_leave_approval'],
            [
                'name' => 'تأیید درخواست مرخصی',
                'subject_type' => Leave::class,
                'is_active' => true,
                'created_by_user_id' => null,
            ]
        );

        // مثل ProcessSampleSeeder: زنجیره‌ی قبلی همین تعریف (اگر از اجرای قبلی
        // seed مانده) پاک می‌شود تا re-seed رکورد تکراری نسازد.
        ProcessTransition::whereIn('from_step_id', $definition->steps()->pluck('id'))->delete();
        $definition->steps()->delete();

        $start = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'start',
            'name' => 'شروع',
            'step_type' => StepType::Start,
        ]);

        $supervisorApproval = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'supervisor_approval',
            'name' => 'تأیید سرپرست/منابع انسانی',
            'step_type' => StepType::Approval,
            'assignment_type' => AssignmentType::Role,
            'assigned_role' => 'accountant',
        ]);

        $durationCheck = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'duration_check',
            'name' => 'بررسی مدت مرخصی',
            'step_type' => StepType::Condition,
            'condition_module_field' => 'days_count',
            'condition_operator' => ConditionOperator::LessThanOrEqual,
            'condition_value' => '5',
        ]);

        $seniorApproval = ProcessStep::create([
            'process_definition_id' => $definition->id,
            'step_key' => 'senior_approval',
            'name' => 'تأیید اضافه‌ی مدیر ارشد',
            'step_type' => StepType::Approval,
            'assignment_type' => AssignmentType::Role,
            'assigned_role' => 'holding_admin',
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
            'to_step_id' => $supervisorApproval->id,
            'on_result' => TransitionResult::Approved,
        ]);

        ProcessTransition::create([
            'from_step_id' => $supervisorApproval->id,
            'to_step_id' => $durationCheck->id,
            'on_result' => TransitionResult::Approved,
        ]);

        ProcessTransition::create([
            'from_step_id' => $supervisorApproval->id,
            'to_step_id' => $endRejected->id,
            'on_result' => TransitionResult::Rejected,
        ]);

        ProcessTransition::create([
            'from_step_id' => $durationCheck->id,
            'to_step_id' => $endApproved->id,
            'on_result' => TransitionResult::ConditionTrue,
        ]);

        ProcessTransition::create([
            'from_step_id' => $durationCheck->id,
            'to_step_id' => $seniorApproval->id,
            'on_result' => TransitionResult::ConditionFalse,
        ]);

        ProcessTransition::create([
            'from_step_id' => $seniorApproval->id,
            'to_step_id' => $endApproved->id,
            'on_result' => TransitionResult::Approved,
        ]);

        ProcessTransition::create([
            'from_step_id' => $seniorApproval->id,
            'to_step_id' => $endRejected->id,
            'on_result' => TransitionResult::Rejected,
        ]);
    }
}
