<?php

use App\Livewire\Core\Parties\PartyForm;
use App\Livewire\Core\Parties\PartyIndex;
use App\Modules\Core\Actions\CreatePartyRecord;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function partyMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function partyGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => partyMakeRole($roleName)->id,
    ]);
}

function partyActingAsWithRole(string $roleName): array
{
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $user = User::factory()->create(['is_super_admin' => false]);
    partyGiveRole($user, $company, $roleName);

    return [$user, $company];
}

it('rejects a party with neither is_customer nor is_supplier at the model/database layer', function () {
    [$user, $company] = partyActingAsWithRole('operator');

    expect(fn () => app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'بدون نقش',
        'party_type' => 'individual',
        'is_customer' => false,
        'is_supplier' => false,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user))->toThrow(RuntimeException::class);

    expect(Party::withoutGlobalScopes()->where('name', 'بدون نقش')->exists())->toBeFalse();
});

it('rejects a party form submission with neither role selected at the validation layer', function () {
    [$user, $company] = partyActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(PartyForm::class)
        ->set('name', 'بدون نقش')
        ->set('is_customer', false)
        ->set('is_supplier', false)
        ->call('save')
        ->assertHasErrors('is_customer');

    expect(Party::withoutGlobalScopes()->where('name', 'بدون نقش')->exists())->toBeFalse();
});

it('allows an operator to create a customer party and see it in the list', function () {
    [$user, $company] = partyActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    Livewire::test(PartyForm::class)
        ->set('name', 'مشتری تست')
        ->set('phone', '09121234567')
        ->set('is_customer', true)
        ->call('save')
        ->assertHasNoErrors();

    $party = Party::where('name', 'مشتری تست')->firstOrFail();
    expect($party->is_customer)->toBeTrue();
    expect($party->is_supplier)->toBeFalse();

    Livewire::test(PartyIndex::class)
        ->set('search', 'مشتری')
        ->assertSee('مشتری تست');
});

it('filters parties by type', function () {
    [$user, $company] = partyActingAsWithRole('operator');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'فقط مشتری',
        'party_type' => 'individual',
        'is_customer' => true,
        'is_supplier' => false,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user);

    app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'فقط تامین‌کننده',
        'party_type' => 'individual',
        'is_customer' => false,
        'is_supplier' => true,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user);

    Livewire::test(PartyIndex::class)
        ->set('typeFilter', 'customer')
        ->assertSee('فقط مشتری')
        ->assertDontSee('فقط تامین‌کننده');
});

it('forbids a viewer role from creating or updating a party but allows viewing', function () {
    [$user, $company] = partyActingAsWithRole('viewer');
    $this->actingAs($user);
    session(['active_company_id' => $company->id]);

    $this->get('/parties')->assertOk();
    $this->get('/parties/create')->assertForbidden();

    expect(fn () => app(CreatePartyRecord::class)->handle([
        'owner_company_id' => $company->id,
        'name' => 'نفوذی',
        'party_type' => 'individual',
        'is_customer' => true,
        'is_supplier' => false,
        'phone' => null,
        'email' => null,
        'economic_code' => null,
        'address' => null,
    ], $user))->toThrow(AuthorizationException::class);
});

it('forbids a user with no role in any company from viewing parties', function () {
    $user = User::factory()->create(['is_super_admin' => false]);
    $this->actingAs($user);

    $this->get('/parties')->assertForbidden();
});
