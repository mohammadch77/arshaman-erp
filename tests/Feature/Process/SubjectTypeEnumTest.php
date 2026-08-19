<?php

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\HR\Models\Leave;
use App\Modules\Process\Enums\ProcessStatus;
use App\Modules\Process\Models\ProcessDefinition;
use App\Modules\Process\Models\ProcessInstance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * بخش ۱ بازطراحی — subject_type از VARCHAR آزاد به ENUM('Leave') محدود شد.
 */
it('accepts the whitelisted subject_type value', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ste-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تست ENUM',
        'process_key' => 'ste_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    expect($definition->subject_type)->toBe(Leave::class);
});

it('rejects a non-whitelisted subject_type value at the database level', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        // همان الگوی سایر CHECK های دستی پروژه: SQLite این نوع ENUM را با
        // یک rebuild ساخته، ولی رفتار دقیق reject کردن مقدار نامعتبر روی
        // sqlite قابل‌اعتماد نیست — تأیید واقعی روی mysql انجام می‌شود.
        test()->markTestSkipped('رفتار ENUM دقیق فقط روی mysql واقعی قابل تأیید است.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ste2-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تست ENUM نامعتبر',
        'process_key' => 'ste2_'.uniqid(),
        'subject_type' => 'App\\Modules\\NotAModule\\Models\\Fake',
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]))->toThrow(QueryException::class);
});

it('accepts the whitelisted subject_type value on process_instances too', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'ste3-'.uniqid(), 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => false]);

    $definition = ProcessDefinition::create([
        'owner_company_id' => $company->id,
        'name' => 'تست ENUM instance',
        'process_key' => 'ste3_'.uniqid(),
        'subject_type' => Leave::class,
        'is_active' => true,
        'created_by_user_id' => $admin->id,
    ]);

    $instance = ProcessInstance::create([
        'owner_company_id' => $company->id,
        'process_definition_id' => $definition->id,
        'subject_type' => Leave::class,
        'subject_id' => (string) Str::uuid(),
        'status' => ProcessStatus::InProgress,
        'started_by_user_id' => $admin->id,
        'started_at' => now(),
    ]);

    expect($instance->subject_type)->toBe(Leave::class);
});
