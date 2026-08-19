<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Process\Actions\CreateProcessDefinition;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Enums\TransitionResult;
use App\Modules\Process\Models\ProcessFormField;
use Illuminate\Support\Facades\Schema;

function ffmHoldingAdmin(Company $company): User
{
    $role = Role::firstOrCreate(['name' => 'holding_admin'], ['display_name' => 'holding_admin', 'is_system' => true]);
    $admin = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create(['user_id' => $admin->id, 'owner_company_id' => $company->id, 'assigned_role_id' => $role->id]);

    return $admin;
}

/**
 * بخش ۲ بازطراحی — process_form_fields جایگزین واقعی request_form_fields/
 * step_form_fields (هر دو ستون JSON قبلی) است. این تست‌ها هم عدم‌وجود ستون‌های
 * قدیمی را تأیید می‌کنند هم این‌که هیچ فیلدی هنگام ساخت یک تعریف گم نمی‌شود.
 */
it('no longer has request_form_fields or step_form_fields columns', function () {
    expect(Schema::hasColumn('process_definitions', 'request_form_fields'))->toBeFalse()
        ->and(Schema::hasColumn('process_steps', 'step_form_fields'))->toBeFalse();
});

it('persists every request-form field and step-form field into process_form_fields without loss', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ffm-'.uniqid(), 'business_type' => 'project_services']);
    $admin = ffmHoldingAdmin($company);

    $payload = [
        'name' => 'فرم کامل',
        'process_key' => 'ffm_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [
            ['key' => 'title', 'label' => 'عنوان', 'type' => 'text'],
            ['key' => 'qty', 'label' => 'تعداد', 'type' => 'number'],
            ['key' => 'kind', 'label' => 'نوع', 'type' => 'select', 'options' => [['value' => 'a', 'label' => 'الف'], ['value' => 'b', 'label' => 'ب']]],
        ],
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            [
                'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval->value,
                'assignment_type' => 'role', 'assigned_role' => 'holding_admin',
                'step_form_fields' => [['key' => 'note', 'label' => 'یادداشت', 'type' => 'textarea']],
            ],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'approval', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Rejected->value],
        ],
    ];

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload);

    expect($definition->formFields)->toHaveCount(3);
    $kind = $definition->formFields->firstWhere('field_key', 'kind');
    expect($kind->field_type)->toBe('select')
        ->and($kind->options)->toBe([['value' => 'a', 'label' => 'الف'], ['value' => 'b', 'label' => 'ب']]);

    $approvalStep = $definition->steps->firstWhere('step_key', 'approval');
    expect($approvalStep->formFields)->toHaveCount(1)
        ->and($approvalStep->formFields->first()->field_key)->toBe('note');
});

it('isolates process_form_fields correctly between a definition and its steps with the same field key', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ffm2-'.uniqid(), 'business_type' => 'project_services']);
    $admin = ffmHoldingAdmin($company);

    $payload = [
        'name' => 'کلید یکسان',
        'process_key' => 'ffm2_'.uniqid(),
        'subject_type' => null,
        'request_form_fields' => [['key' => 'note', 'label' => 'یادداشت درخواست', 'type' => 'text']],
        'is_active' => true,
        'steps' => [
            ['step_key' => 'start', 'name' => 'شروع', 'step_type' => StepType::Start->value],
            [
                'step_key' => 'approval', 'name' => 'تأیید', 'step_type' => StepType::Approval->value,
                'assignment_type' => 'role', 'assigned_role' => 'holding_admin',
                'step_form_fields' => [['key' => 'note', 'label' => 'یادداشت مرحله', 'type' => 'text']],
            ],
            ['step_key' => 'end', 'name' => 'پایان', 'step_type' => StepType::End->value],
        ],
        'transitions' => [
            ['from_step_key' => 'start', 'to_step_key' => 'approval', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Approved->value],
            ['from_step_key' => 'approval', 'to_step_key' => 'end', 'on_result' => TransitionResult::Rejected->value],
        ],
    ];

    $definition = app(CreateProcessDefinition::class)->handle($admin, $company->id, $payload);

    expect(ProcessFormField::where('field_key', 'note')->count())->toBe(2);
    expect($definition->formFields->first()->label)->toBe('یادداشت درخواست');
    expect($definition->steps->firstWhere('step_key', 'approval')->formFields->first()->label)->toBe('یادداشت مرحله');
});
