<?php

use App\Livewire\Process\NewProcessRequest;
use App\Livewire\Process\ProcessDefinitionForm;
use App\Livewire\Process\ProcessDefinitionIndex;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use Livewire\Livewire;

/**
 * بخش ۴.۲ Session جاری — نسخه‌بندی سرتاسری از UI واقعی: instance های قدیمی
 * باید همچنان به نسخه‌ی قدیمی اشاره کنند، درخواست‌های تازه از نسخه‌ی جدید
 * می‌روند، و اتصال HR/مرخصی (ProcessEngine::startForSubjectIfActive) همیشه
 * فقط نسخه‌ی جاری را resolve می‌کند.
 */
function pvRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pvGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => pvRole($roleName)->id,
    ]);
}

it('creates a new version through the real ProcessDefinitionForm component and keeps old-version instance history untouched', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pv-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    pvGiveRole($admin, $company, 'holding_admin');

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $v1 = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'فرایند نسخه‌بندی‌شده',
        'process_key' => 'pv_test_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    expect($v1->version)->toBe(1)->and($v1->is_current_version)->toBeTrue();

    $startStep = $v1->steps()->where('step_type', StepType::Start->value)->first();
    $oldInstance = ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $v1->id,
        'subject_type' => null,
        'subject_id' => null,
        'current_step_id' => $startStep->id,
        'status' => ProcessStatus::Approved->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    // ویرایش واقعی از طریق خودِ کامپوننت Livewire — نه فقط Action مستقیم.
    $component = Livewire::test(ProcessDefinitionForm::class, ['processDefinition' => $v1->id])
        ->assertSee('ذخیره یک نسخه‌ی جدید می‌سازد')
        ->set('name', 'فرایند نسخه‌بندی‌شده (ویرایش‌شده)')
        ->call('save');

    $component->assertHasNoErrors();

    $v1->refresh();
    expect($v1->is_current_version)->toBeFalse()
        ->and($v1->version)->toBe(1);

    $v2 = ProcessDefinition::where('process_key', $v1->process_key)->where('is_current_version', true)->first();

    expect($v2)->not->toBeNull()
        ->and($v2->id)->not->toBe($v1->id)
        ->and($v2->version)->toBe(2)
        ->and($v2->name)->toBe('فرایند نسخه‌بندی‌شده (ویرایش‌شده)');

    // instance قدیمی همچنان به نسخه‌ی قدیمی اشاره دارد — دست‌نخورده.
    $oldInstance->refresh();
    expect($oldInstance->process_definition_id)->toBe($v1->id);

    // فهرست تعاریف فقط نسخه‌ی جاری را نشان می‌دهد.
    Livewire::test(ProcessDefinitionIndex::class)
        ->assertSee('فرایند نسخه‌بندی‌شده (ویرایش‌شده)')
        ->assertDontSee('پیدا نشد');

    $visibleCount = ProcessDefinition::where('process_key', $v1->process_key)->where('is_current_version', true)->count();
    expect($visibleCount)->toBe(1);
});

it('lists only the current version of a free-form process in the new-request panel', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pv2-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    pvGiveRole($admin, $company, 'holding_admin');

    $v1 = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'درخواست آزاد نسخه‌بندی',
        'process_key' => 'pv2_test_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    // یک instance تمام‌شده تا فقط انتخاب نسخه‌ی جدید فعال شود.
    $startStep = $v1->steps()->where('step_type', StepType::Start->value)->first();
    ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $v1->id,
        'subject_type' => null,
        'subject_id' => null,
        'current_step_id' => $startStep->id,
        'status' => ProcessStatus::Approved->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $v2 = app(\App\Modules\Process\Actions\CreateProcessDefinitionVersion::class)->handle($admin, $v1->fresh(), [
        'name' => 'درخواست آزاد نسخه‌بندی',
        'subject_type' => null,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
        ],
    ]);

    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $regularUser = User::factory()->create(['is_super_admin' => false]);
    pvGiveRole($regularUser, $company, 'operator');
    test()->actingAs($regularUser);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(NewProcessRequest::class)
        ->call('selectDefinition', $v2->id)
        ->assertSet('selectedDefinitionId', $v2->id);

    $ids = Livewire::test(NewProcessRequest::class)->instance()->definitions->pluck('id')->all();
    expect($ids)->toContain($v2->id)->not->toContain($v1->id);
});

it('resolves the current version, not an old one, when the HR leave process integration auto-starts an instance', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pv3-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    pvGiveRole($admin, $company, 'holding_admin');

    $v1 = app(CreateProcessDefinition::class)->handle($admin, $company->id, [
        'name' => 'تأیید مرخصی نسخه‌بندی',
        'process_key' => 'pv3_leave_'.uniqid(),
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'accountant'],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
            ['step_key' => 'end_rejected', 'name' => 'پایان ردشده', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'approval', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
        ],
    ]);

    // یک instance تمام‌شده روی نسخه‌ی اول تا بتوانیم نسخه‌ی دوم بسازیم.
    $v1StartStep = $v1->steps()->where('step_type', StepType::Start->value)->first();
    ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $v1->id,
        'subject_type' => Leave::class,
        'subject_id' => (string) \Illuminate\Support\Str::uuid(),
        'current_step_id' => $v1StartStep->id,
        'status' => ProcessStatus::Approved->value,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
        'completed_at' => now(),
    ]);

    $v2 = app(\App\Modules\Process\Actions\CreateProcessDefinitionVersion::class)->handle($admin, $v1->fresh(), [
        'name' => 'تأیید مرخصی نسخه‌بندی (نسخه ۲)',
        'subject_type' => Leave::class,
        'request_form_fields' => null,
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            ['step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval->value, 'assignment_type' => 'role', 'assigned_role' => 'accountant'],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
            ['step_key' => 'end_rejected', 'name' => 'پایان ردشده', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'approval', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end_rejected', 'on_result' => TransitionResult::Rejected->value],
        ],
    ]);

    $employeeUser = User::factory()->create(['is_super_admin' => false]);
    $employee = \App\Modules\HR\Models\Employee::factory()->create([
        'owner_company_id' => $company->id,
        'user_id' => $employeeUser->id,
    ]);

    $leave = Leave::create([
        'owner_company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type' => 'annual',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'days_count' => 2,
        'reason' => 'تست نسخه‌بندی',
        'leave_status' => 'pending',
        'requested_by_user_id' => $employeeUser->id,
    ]);

    $instance = app(ProcessEngine::class)->startForSubjectIfActive($leave, $employeeUser);

    expect($instance)->not->toBeNull()
        ->and($instance->process_definition_id)->toBe($v2->id)
        ->and($instance->process_definition_id)->not->toBe($v1->id);
});
