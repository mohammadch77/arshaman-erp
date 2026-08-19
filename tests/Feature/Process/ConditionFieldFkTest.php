<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Enums\ConditionOperator;
use App\Modules\Process\Enums\StepType;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessFormField;
use App\Modules\Process\Models\ProcessStep;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * بخش ۳ بازطراحی — condition_field (VARCHAR آزاد) حذف و با condition_field_id
 * (FK واقعی) / condition_module_field جایگزین شد.
 */
it('no longer has a condition_field column on process_steps', function () {
    expect(Schema::hasColumn('process_steps', 'condition_field'))->toBeFalse()
        ->and(Schema::hasColumn('process_steps', 'condition_field_id'))->toBeTrue()
        ->and(Schema::hasColumn('process_steps', 'condition_module_field'))->toBeTrue();
});

it('resolves a free-form condition through a real FK to process_form_fields', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'cff-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'شرط آزاد',
        'process_key' => 'cff_'.uniqid(),
        'subject_type' => null,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $field = ProcessFormField::create([
        'formable_type' => ProcessFormField::FORMABLE_DEFINITION,
        'formable_id' => $definition->id,
        'field_key' => 'amount',
        'label' => 'مبلغ',
        'field_type' => 'number',
        'is_required' => true,
    ]);

    $condition = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'cond',
        'name' => 'شرط',
        'step_type' => StepType::Condition,
        'condition_field_id' => $field->id,
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '100',
    ]);

    expect($condition->conditionField->field_key)->toBe('amount')
        ->and($condition->condition_module_field)->toBeNull();
});

it('resolves a module-linked condition through condition_module_field, not the FK', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'cff2-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'شرط ماژول',
        'process_key' => 'cff2_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $condition = ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'cond',
        'name' => 'شرط',
        'step_type' => StepType::Condition,
        'condition_module_field' => 'days_count',
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '5',
    ]);

    expect($condition->condition_module_field)->toBe('days_count')
        ->and($condition->condition_field_id)->toBeNull()
        ->and($condition->conditionField)->toBeNull();
});

it('rejects a condition step with both or neither condition source set, at the database level', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('CHECK دستی این ستون فقط روی mysql واقعی اعمال می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'cff3-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'شرط نامعتبر',
        'process_key' => 'cff3_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    expect(fn () => ProcessStep::create([
        'process_definition_id' => $definition->id,
        'step_key' => 'cond',
        'name' => 'شرط',
        'step_type' => StepType::Condition,
        'condition_operator' => ConditionOperator::GreaterThan,
        'condition_value' => '5',
    ]))->toThrow(QueryException::class);
});
