<?php

use App\Livewire\Process\ProcessDefinitionForm;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Enums\StepType;
use Livewire\Livewire;

/**
 * بخش ۵ بازطراحی — درگ‌اند‌دراپ فیلدهای فرم (درخواست/مرحله) و خودِ مراحل، فقط
 * ترتیب نمایش را عوض می‌کند (بدون اثر روی کلید/برچسب/نوع یا نتیجه‌ی اجرا).
 */
function pffdRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function pffdAdmin(): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'pffd-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create(['user_id' => $admin->id, 'owner_company_id' => $company->id, 'assigned_role_id' => pffdRole('holding_admin')->id]);

    return [$company, $admin];
}

it('reorders request form fields via moveRequestFieldRow without changing their content', function () {
    [$company, $admin] = pffdAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->set('subjectType', '')
        ->call('addRequestField')
        ->set('requestFormFields.0.key', 'first')
        ->set('requestFormFields.0.label', 'اول')
        ->call('addRequestField')
        ->set('requestFormFields.1.key', 'second')
        ->set('requestFormFields.1.label', 'دوم');

    $component->call('moveRequestFieldRow', 'first', 1);

    $keys = collect($component->get('requestFormFields'))->pluck('key')->all();
    expect($keys)->toBe(['second', 'first']);

    $labels = collect($component->get('requestFormFields'))->pluck('label')->all();
    expect($labels)->toBe(['دوم', 'اول']);
});

it('reorders step_form_fields for one step via moveStepFormFieldRow', function () {
    [$company, $admin] = pffdAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->call('addStep')
        ->set('steps.1.step_type', StepType::Approval->value)
        ->call('addStepFormField', 1)
        ->set('steps.1.step_form_fields.0.key', 'a')
        ->call('addStepFormField', 1)
        ->set('steps.1.step_form_fields.1.key', 'b');

    $component->call('moveStepFormFieldRow', 1, 'a', 1);

    $keys = collect($component->get('steps.1.step_form_fields'))->pluck('key')->all();
    expect($keys)->toBe(['b', 'a']);
});

it('reorders steps via moveStepRow but never moves the start step', function () {
    [$company, $admin] = pffdAdmin();
    test()->actingAs($admin);
    app(CompanyContext::class)->set($company->id);

    $component = Livewire::test(ProcessDefinitionForm::class)
        ->call('addStep')
        ->set('steps.1.step_key', 'approval_a')
        ->call('addStep')
        ->set('steps.2.step_key', 'approval_b');

    $startKey = collect($component->get('steps'))->firstWhere('step_type', StepType::Start->value)['step_key'];

    // تلاش برای جابه‌جایی مرحله‌ی start باید بی‌اثر بماند.
    $component->call('moveStepRow', $startKey, 2);
    expect(collect($component->get('steps'))->pluck('step_key')->first())->toBe($startKey);

    // جابه‌جایی یک مرحله‌ی غیر-start مجاز است.
    $component->call('moveStepRow', 'approval_b', 1);
    $keys = collect($component->get('steps'))->pluck('step_key')->all();
    expect($keys)->toBe([$startKey, 'approval_b', 'approval_a']);
});
