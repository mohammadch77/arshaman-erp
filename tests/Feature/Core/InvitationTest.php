<?php

use App\Livewire\Core\Auth\AcceptInvitation;
use App\Livewire\Core\Users\InviteUser;
use App\Mail\UserInvitationMail;
use App\Modules\Core\Actions\AcceptInvitation as AcceptInvitationAction;
use App\Modules\Core\Actions\InviteUser as InviteUserAction;
use App\Modules\Core\Exceptions\InvalidInvitationException;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Models\UserInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('rejects invitation sent by a non-admin actor', function () {
    Mail::fake();

    $intruder = User::factory()->create(['is_super_admin' => false]);

    expect(fn () => app(InviteUserAction::class)->handle([
        'email' => 'nobody@example.test',
        'full_name' => 'کسی',
    ], $intruder))->toThrow(AuthorizationException::class);

    expect(UserInvitation::where('email', 'nobody@example.test')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('allows an admin to invite a user and sends the invitation email', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_super_admin' => true]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);

    $this->actingAs($admin);

    Livewire::test(InviteUser::class)
        ->set('full_name', 'کاربر دعوت‌شده')
        ->set('email', 'invitee@example.test')
        ->set('companyId', $company->id)
        ->set('roleId', $role->id)
        ->call('invite')
        ->assertHasNoErrors();

    $invitation = UserInvitation::where('email', 'invitee@example.test')->firstOrFail();

    expect($invitation->token)->not->toBeEmpty();
    expect($invitation->expires_at->isFuture())->toBeTrue();
    expect($invitation->owner_company_id)->toBe($company->id);

    Mail::assertSent(UserInvitationMail::class, fn ($mail) => $mail->hasTo('invitee@example.test'));
});

it('accepts a valid invitation and creates the user with the assigned role×company', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $role = Role::create(['name' => 'operator', 'display_name' => 'اپراتور', 'is_system' => true]);

    $invitation = UserInvitation::create([
        'email' => 'accepted@example.test',
        'full_name' => 'کاربر پذیرفته‌شده',
        'token' => 'valid-token',
        'owner_company_id' => $company->id,
        'assigned_role_id' => $role->id,
        'invited_by_user_id' => $admin->id,
        'expires_at' => now()->addDay(),
        'created_at' => now(),
    ]);

    Livewire::test(AcceptInvitation::class, ['token' => 'valid-token'])
        ->set('full_name', 'کاربر پذیرفته‌شده')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('accept')
        ->assertHasNoErrors();

    $user = User::where('email', 'accepted@example.test')->firstOrFail();
    expect($user->full_name)->toBe('کاربر پذیرفته‌شده');
    expect(UserCompanyRole::where('user_id', $user->id)->where('owner_company_id', $company->id)->exists())->toBeTrue();
    expect($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('rejects an invalid, expired, or already-used token', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    expect(fn () => app(AcceptInvitationAction::class)->handle('does-not-exist', [
        'full_name' => 'کسی', 'password' => 'password123',
    ]))->toThrow(InvalidInvitationException::class);

    $expired = UserInvitation::create([
        'email' => 'expired@example.test',
        'full_name' => 'کاربر منقضی',
        'token' => 'expired-token',
        'invited_by_user_id' => $admin->id,
        'expires_at' => now()->subHour(),
        'created_at' => now()->subDays(4),
    ]);

    expect(fn () => app(AcceptInvitationAction::class)->handle('expired-token', [
        'full_name' => 'کاربر منقضی', 'password' => 'password123',
    ]))->toThrow(InvalidInvitationException::class);

    $used = UserInvitation::create([
        'email' => 'used@example.test',
        'full_name' => 'کاربر استفاده‌شده',
        'token' => 'used-token',
        'invited_by_user_id' => $admin->id,
        'expires_at' => now()->addDay(),
        'accepted_at' => now(),
        'created_at' => now(),
    ]);

    expect(fn () => app(AcceptInvitationAction::class)->handle('used-token', [
        'full_name' => 'کاربر استفاده‌شده', 'password' => 'password123',
    ]))->toThrow(InvalidInvitationException::class);

    expect(User::where('email', 'expired@example.test')->exists())->toBeFalse();
    expect(User::where('email', 'used@example.test')->exists())->toBeFalse();
});

it('shows an invalid message on the accept-invitation page for a bad token', function () {
    Livewire::test(AcceptInvitation::class, ['token' => 'no-such-token'])
        ->assertSet('invitationIsValid', false);
});
