<?php

use App\Livewire\Process\MyProcessTasks;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Enums\AssignmentType;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessStep;
use App\Modules\Process\Models\ProcessTransition;
use App\Modules\Process\Services\ProcessEngine;
use Livewire\Livewire;

/**
 * بخش ۵ Session جاری — بررسی صریح گزارش «۴۰۳ برای دو نفر با یک نقش»: دو
 * مرحله‌ی تأیید جدا، هر دو واگذارشده به همان نقش (accountant)، هر مرحله
 * توسط یک کاربر واقعاً متفاوت (نه همان کاربر) از طریق خودِ کامپوننت واقعی
 * MyProcessTasks (نه فقط Action مستقیم) تأیید می‌شود. طبق بررسی کد
 * (ProcessEngine::assertActorAuthorizedForStep) تخصیص role هرگز به یک
 * user_id مشخص مقید نمی‌شود — هر کاربری با آن نقش در همان شرکت مجاز است؛
 * این تست همان نتیجه را در عمل، از مسیر UI واقعی، ثابت می‌کند.
 */
function tusrRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function tusrGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => tusrRole($roleName)->id,
    ]);
}

it('lets two different real users who both hold the same role approve two different steps of the same instance without any 403', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'tusr-'.uniqid(), 'business_type' => 'project_services']);
    $creator = User::factory()->create(['is_super_admin' => true]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'دو تأیید هم‌نقش',
        'process_key' => 'tusr_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [],
        'is_active' => true,
        'created_by_user_id' => $creator->id,
    ]);

    $start = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start]);
    $approval1 = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval1', 'name' => 'تأیید اول', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $approval2 = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'approval2', 'name' => 'تأیید دوم', 'step_type' => StepType::Approval, 'assignment_type' => AssignmentType::Role, 'assigned_role' => 'accountant']);
    $end = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End]);
    $endRejected = ProcessStep::create(['process_definition_id' => $definition->id, 'step_key' => 'end_rejected', 'name' => 'پایان — ردشده', 'step_type' => StepType::End]);

    ProcessTransition::create(['from_step_id' => $start->id, 'to_step_id' => $approval1->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval1->id, 'to_step_id' => $approval2->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval1->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);
    ProcessTransition::create(['from_step_id' => $approval2->id, 'to_step_id' => $end->id, 'on_result' => TransitionResult::Approved]);
    ProcessTransition::create(['from_step_id' => $approval2->id, 'to_step_id' => $endRejected->id, 'on_result' => TransitionResult::Rejected]);

    $instance = app(ProcessEngine::class)->startInstance($definition, $creator);
    expect($instance->current_step_id)->toBe($approval1->id);

    // دو کاربر واقعاً متفاوت، هر دو accountant در همین شرکت — نه همان کاربر.
    $accountantA = User::factory()->create(['is_super_admin' => false]);
    tusrGiveRole($accountantA, $company, 'accountant');

    $accountantB = User::factory()->create(['is_super_admin' => false]);
    tusrGiveRole($accountantB, $company, 'accountant');

    expect($accountantA->id)->not->toBe($accountantB->id);

    // کاربر الف مرحله‌ی اول را از طریق خودِ کامپوننت واقعی MyProcessTasks تأیید می‌کند.
    test()->actingAs($accountantA);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->call('approve')
        ->assertHasNoErrors();

    $instance->refresh();
    expect($instance->status)->toBe(ProcessStatus::InProgress);
    expect($instance->current_step_id)->toBe($approval2->id);

    // کاربر ب (متفاوت، همان نقش) مرحله‌ی دوم همان instance را تأیید می‌کند —
    // نباید هیچ ۴۰۳ ای بگیرد، چون تخصیص role هرگز به user_id کاربر الف مقید نبود.
    test()->actingAs($accountantB);
    app(CompanyContext::class)->set($company->id);

    Livewire::test(MyProcessTasks::class)
        ->call('openComment', $instance->id)
        ->call('approve')
        ->assertHasNoErrors();

    $instance->refresh();
    expect($instance->status)->toBe(ProcessStatus::Approved);
});
