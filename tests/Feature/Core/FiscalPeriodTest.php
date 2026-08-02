<?php

use App\Modules\Core\Actions\CloseFiscalPeriod;
use App\Modules\Core\Actions\CreateFiscalPeriod;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\FiscalPeriod;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function fiscalMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function fiscalActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => fiscalMakeRole($roleName)->id,
    ]);

    return [$user, $company];
}

it('computes the start and end dates of a fiscal year from farvardin 1 to the last day of esfand, leap-year aware', function () {
    [$user, $company] = fiscalActingAsWithRole('holding_admin');

    // ۱۴۰۳ کبیسه است (اسفند ۳۰ روزه)، ۱۴۰۴ کبیسه نیست (اسفند ۲۹ روزه).
    $leapPeriod = app(CreateFiscalPeriod::class)->handle($company->id, 1403, $user);
    $normalPeriod = app(CreateFiscalPeriod::class)->handle($company->id, 1404, $user);

    expect($leapPeriod->start_date->toDateString())->toBe('2024-03-20');
    expect($leapPeriod->end_date->toDateString())->toBe('2025-03-20');

    expect($normalPeriod->start_date->toDateString())->toBe('2025-03-21');
    expect($normalPeriod->end_date->toDateString())->toBe('2026-03-20');
});

it('prevents closing a fiscal period that is already closed', function () {
    [$user, $company] = fiscalActingAsWithRole('holding_admin');

    $period = app(CreateFiscalPeriod::class)->handle($company->id, 1404, $user);
    app(CloseFiscalPeriod::class)->handle($period, $user);

    expect(fn () => app(CloseFiscalPeriod::class)->handle($period->fresh(), $user))
        ->toThrow(ValidationException::class);
});

it('only allows a holding_admin to close a fiscal period', function () {
    [$user, $company] = fiscalActingAsWithRole('accountant');

    $period = app(CreateFiscalPeriod::class)->handle($company->id, 1404, User::factory()->create(['is_super_admin' => true]));

    expect(fn () => app(CloseFiscalPeriod::class)->handle($period, $user))
        ->toThrow(AuthorizationException::class);

    expect($period->fresh()->is_closed)->toBeFalse();
});

it('lets a holding_admin close a fiscal period and records who closed it', function () {
    [$user, $company] = fiscalActingAsWithRole('holding_admin');

    $period = app(CreateFiscalPeriod::class)->handle($company->id, 1404, $user);

    $closed = app(CloseFiscalPeriod::class)->handle($period, $user);

    expect($closed->is_closed)->toBeTrue();
    expect($closed->closed_by_user_id)->toBe($user->id);
    expect($closed->closed_at)->not->toBeNull();
});

it('only allows a holding_admin to create a fiscal period', function () {
    [$user, $company] = fiscalActingAsWithRole('operator');

    expect(fn () => app(CreateFiscalPeriod::class)->handle($company->id, 1404, $user))
        ->toThrow(AuthorizationException::class);

    expect(FiscalPeriod::withoutGlobalScopes()->where('owner_company_id', $company->id)->count())->toBe(0);
});
