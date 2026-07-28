<?php

use App\Livewire\Core\Users\AssignRole;
use App\Livewire\Core\Users\UserCreate;
use App\Livewire\Core\Users\UserIndex;
use App\Modules\Core\Actions\AssignRole as AssignRoleAction;
use App\Modules\Core\Actions\CreateUser;
use App\Modules\Core\Actions\ToggleUserActive;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function makeRole(string $name): Role
{
    return Role::create(['name' => $name, 'display_name' => $name, 'is_system' => true]);
}

it('forbids a normal user from accessing user management', function () {
    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user)->get('/users')->assertForbidden();
    $this->actingAs($user)->get('/users/create')->assertForbidden();
    $this->actingAs($user)->get('/users/roles')->assertForbidden();
});

it('allows a super admin to create a user and assign a role×company', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $role = makeRole('operator');

    $this->actingAs($admin);

    Livewire::test(UserCreate::class)
        ->set('full_name', 'کاربر تست')
        ->set('email', 'newuser@example.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'newuser@example.test')->firstOrFail();
    expect($newUser->full_name)->toBe('کاربر تست');

    Livewire::test(AssignRole::class)
        ->set('userId', $newUser->id)
        ->set('companyId', $company->id)
        ->set('roleId', $role->id)
        ->call('assign')
        ->assertHasNoErrors();

    expect(UserCompanyRole::where('user_id', $newUser->id)->where('owner_company_id', $company->id)->exists())->toBeTrue();
});

it('allows a holding_admin to access user management', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $role = makeRole('holding_admin');

    $admin = User::factory()->create(['is_super_admin' => false]);
    UserCompanyRole::create(['user_id' => $admin->id, 'owner_company_id' => $company->id, 'assigned_role_id' => $role->id]);

    $this->actingAs($admin)->get('/users')->assertOk();
});

it('logs user creation and status changes in the activity log', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $this->actingAs($admin);

    Livewire::test(UserCreate::class)
        ->set('full_name', 'کاربر لاگ')
        ->set('email', 'logged@example.test')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save');

    $newUser = User::where('email', 'logged@example.test')->firstOrFail();

    expect(Activity::where('subject_id', $newUser->id)->where('event', 'created')->exists())->toBeTrue();

    Livewire::test(UserIndex::class)
        ->call('toggleActive', $newUser->id);

    expect($newUser->refresh()->is_active)->toBeFalse();
    expect(Activity::where('subject_id', $newUser->id)->where('event', 'updated')->exists())->toBeTrue();
});

it('rejects Action calls made by an unauthorized actor, even bypassing Livewire entirely', function () {
    $intruder = User::factory()->create(['is_super_admin' => false]);
    $target = User::factory()->create(['is_super_admin' => false]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $role = makeRole('operator');

    expect(fn () => app(CreateUser::class)->handle([
        'full_name' => 'نفوذی',
        'email' => 'intruder-made@example.test',
        'password' => 'password123',
    ], $intruder))->toThrow(AuthorizationException::class);

    expect(User::where('email', 'intruder-made@example.test')->exists())->toBeFalse();

    expect(fn () => app(AssignRoleAction::class)->handle($target, $company->id, $role->id, $intruder))
        ->toThrow(AuthorizationException::class);

    expect(UserCompanyRole::where('user_id', $target->id)->where('owner_company_id', $company->id)->exists())->toBeFalse();

    expect(fn () => app(ToggleUserActive::class)->handle($target, $intruder))
        ->toThrow(AuthorizationException::class);

    expect($target->refresh()->is_active)->toBeTrue();
});
