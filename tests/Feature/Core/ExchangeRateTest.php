<?php

use App\Livewire\Core\Currencies\ExchangeRateForm;
use App\Modules\Core\Actions\RecordExchangeRate;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\ExchangeRate;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\ExchangeRateResolver;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function xrateMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function xrateActingAsWithRole(string $roleName): User
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);

    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => xrateMakeRole($roleName)->id,
    ]);

    return $user;
}

it('resolves the exact rate when a record exists for the given date', function () {
    $currency = Currency::create(['code' => 'USD', 'name' => 'دلار آمریکا']);

    ExchangeRate::create([
        'currency_id' => $currency->id,
        'rate_to_toman' => '58000.00',
        'effective_date' => '2026-08-01',
    ]);

    $rate = app(ExchangeRateResolver::class)->rate($currency->id, Carbon::parse('2026-08-01'));

    expect($rate)->toBe('58000.00');
});

it('falls back to the most recent prior rate when no exact-date record exists', function () {
    $currency = Currency::create(['code' => 'USD', 'name' => 'دلار آمریکا']);

    ExchangeRate::create(['currency_id' => $currency->id, 'rate_to_toman' => '57000.00', 'effective_date' => '2026-07-28']);
    ExchangeRate::create(['currency_id' => $currency->id, 'rate_to_toman' => '58500.00', 'effective_date' => '2026-08-01']);

    $rate = app(ExchangeRateResolver::class)->rate($currency->id, Carbon::parse('2026-08-03'));

    expect($rate)->toBe('58500.00');
});

it('throws a clear exception when no rate exists on or before the requested date', function () {
    $currency = Currency::create(['code' => 'USD', 'name' => 'دلار آمریکا']);

    ExchangeRate::create(['currency_id' => $currency->id, 'rate_to_toman' => '58500.00', 'effective_date' => '2026-08-01']);

    expect(fn () => app(ExchangeRateResolver::class)->rate($currency->id, Carbon::parse('2026-07-01')))
        ->toThrow(RuntimeException::class);
});

it('allows an accountant to record a daily exchange rate', function () {
    $user = xrateActingAsWithRole('accountant');
    $currency = Currency::create(['code' => 'EUR', 'name' => 'یورو']);

    $rate = app(RecordExchangeRate::class)->handle([
        'currency_id' => $currency->id,
        'rate_to_toman' => '63000.00',
        'effective_date' => '2026-08-02',
    ], $user);

    expect($rate->rate_to_toman)->toBe('63000.00');
});

it('forbids an operator from recording an exchange rate but allows viewing', function () {
    $user = xrateActingAsWithRole('operator');
    $this->actingAs($user);
    $currency = Currency::create(['code' => 'EUR', 'name' => 'یورو']);

    $this->get('/exchange-rates')->assertOk();
    $this->get('/exchange-rates/create')->assertForbidden();

    expect(fn () => app(RecordExchangeRate::class)->handle([
        'currency_id' => $currency->id,
        'rate_to_toman' => '63000.00',
        'effective_date' => '2026-08-02',
    ], $user))->toThrow(AuthorizationException::class);
});

it('lets any authenticated user view exchange rates regardless of role', function () {
    $user = xrateActingAsWithRole('viewer');
    $this->actingAs($user);

    $this->get('/exchange-rates')->assertOk();
});

it('re-entering the same currency and date overwrites the previous rate instead of duplicating', function () {
    $user = xrateActingAsWithRole('accountant');
    $currency = Currency::create(['code' => 'AED', 'name' => 'درهم امارات']);

    app(RecordExchangeRate::class)->handle([
        'currency_id' => $currency->id,
        'rate_to_toman' => '15000.00',
        'effective_date' => '2026-08-02',
    ], $user);

    app(RecordExchangeRate::class)->handle([
        'currency_id' => $currency->id,
        'rate_to_toman' => '15500.00',
        'effective_date' => '2026-08-02',
    ], $user);

    expect(ExchangeRate::where('currency_id', $currency->id)->count())->toBe(1);
    expect(ExchangeRate::where('currency_id', $currency->id)->first()->rate_to_toman)->toBe('15500.00');
});

it('validates the exchange rate form through the livewire component', function () {
    $user = xrateActingAsWithRole('holding_admin');
    $this->actingAs($user);
    $currency = Currency::create(['code' => 'USD', 'name' => 'دلار آمریکا']);

    Livewire::test(ExchangeRateForm::class)
        ->set('currency_id', $currency->id)
        ->set('rate_to_toman', '59000')
        ->call('save')
        ->assertHasNoErrors();

    expect(ExchangeRate::where('currency_id', $currency->id)->exists())->toBeTrue();
});
