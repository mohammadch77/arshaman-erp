<?php

use App\Livewire\CRM\TicketIndex;
use App\Livewire\CRM\TicketShow;
use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\CRM\Actions\ChangeTicketStatus;
use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Actions\CreateTicket;
use App\Modules\CRM\Actions\ReplyToTicket;
use App\Modules\CRM\Enums\TicketPriority;
use App\Modules\CRM\Enums\TicketStatus;
use App\Modules\CRM\Models\Ticket;
use App\Modules\CRM\Models\TicketReply;
use App\Modules\CRM\Services\ContactMatcher;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function ticketMakeRole(string $name): Role
{
    return Role::firstOrCreate(['name' => $name], ['display_name' => $name, 'is_system' => true]);
}

function ticketGiveRole(User $user, Company $company, string $roleName): void
{
    UserCompanyRole::create([
        'user_id' => $user->id,
        'owner_company_id' => $company->id,
        'assigned_role_id' => ticketMakeRole($roleName)->id,
    ]);
}

function ticketMakeProfile(Company $company, User $actor, string $phone = '09121234567')
{
    return app(CreateContactSiteProfile::class)->handle(
        ['full_name' => 'مشتری تیکت', 'phone' => $phone, 'email' => null, 'site_full_name' => null, 'owner_company_id' => $company->id],
        $actor,
        app(ContactMatcher::class)
    );
}

it('lets an operator create a ticket for a contact site profile', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'مشکل در ورود',
        'description' => 'کاربر نمی‌تواند وارد شود',
        'priority' => TicketPriority::High->value,
    ], $operator);

    expect($ticket->status)->toBe(TicketStatus::Open->value);
    expect($ticket->priority)->toBe(TicketPriority::High->value);
    expect($ticket->owner_company_id)->toBe($company->id);
    expect($ticket->created_by_user_id)->toBe($operator->id);
});

it('rejects ticket creation by an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($intruder, $company, 'sales_rep');

    $profile = ticketMakeProfile($company, $admin, '09121234568');

    expect(fn () => app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'تلاش غیرمجاز',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $intruder))->toThrow(AuthorizationException::class);

    expect(Ticket::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a user who is only a viewer in the target company, even though they are operator in a different company — cross-company role leak regression', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'Tkart', 'slug' => 'tkart', 'business_type' => 'physical_goods']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($user, $companyA, 'operator');
    ticketGiveRole($user, $companyB, 'viewer');

    $profileB = ticketMakeProfile($companyB, $admin, '09121234569');

    expect(fn () => app(CreateTicket::class)->handle([
        'owner_company_id' => $companyB->id,
        'contact_site_profile_id' => $profileB->id,
        'subject' => 'نشتی دسترسی',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $user))->toThrow(AuthorizationException::class);

    expect(Ticket::withoutGlobalScopes()->count())->toBe(0);
});

it('rejects ticket creation by an accountant', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $accountant = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($accountant, $company, 'accountant');

    $profile = ticketMakeProfile($company, $admin, '09121234570');

    expect(fn () => app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'باید رد شود',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $accountant))->toThrow(AuthorizationException::class);
});

it('changes ticket status through valid values', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'موضوع تست',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $operator);

    $ticket = app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::InProgress->value, $operator);
    expect($ticket->status)->toBe(TicketStatus::InProgress->value);

    $ticket = app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Resolved->value, $operator);
    expect($ticket->status)->toBe(TicketStatus::Resolved->value);

    $ticket = app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed->value, $operator);
    expect($ticket->status)->toBe(TicketStatus::Closed->value);
});

it('rejects an invalid ticket status value', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'موضوع تست',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $operator);

    expect(fn () => app(ChangeTicketStatus::class)->handle($ticket, 'archived', $operator))
        ->toThrow(InvalidArgumentException::class);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Open->value);
});

it('allows replying to a ticket that is already closed', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'موضوع بسته‌شده',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $operator);

    $ticket = app(ChangeTicketStatus::class)->handle($ticket, TicketStatus::Closed->value, $operator);
    expect($ticket->isClosed())->toBeTrue();

    $reply = app(ReplyToTicket::class)->handle($ticket, 'پاسخ روی تیکت بسته', $operator);

    expect($reply->ticket_id)->toBe($ticket->id);
    expect($reply->user_id)->toBe($operator->id);
    expect(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(1);
});

it('rejects a reply from an actor without an authorized role, even bypassing Livewire entirely', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    $intruder = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($intruder, $company, 'sales_rep');

    $profile = ticketMakeProfile($company, $admin, '09121234571');

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'موضوع تست',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $admin);

    expect(fn () => app(ReplyToTicket::class)->handle($ticket, 'پاسخ غیرمجاز', $intruder))
        ->toThrow(AuthorizationException::class);

    expect(TicketReply::where('ticket_id', $ticket->id)->count())->toBe(0);
});

it('shows the ticket on the contact 360 profile through TicketTimeline', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $admin = User::factory()->create(['is_super_admin' => true]);
    ticketGiveRole($admin, $company, 'holding_admin');

    $profile = ticketMakeProfile($company, $admin, '09121234572');

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'دیده‌شدن در پروفایل ۳۶۰',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $admin);

    $this->actingAs($admin);
    session(['active_company_id' => $company->id]);

    $response = $this->get(route('contacts.profile', ['contactId' => $profile->contact_id]));

    $response->assertOk();
    $response->assertSee($ticket->subject);
});

it('lets an operator create a ticket through the Livewire TicketIndex component', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(TicketIndex::class)
        ->assertOk()
        ->set('contact_site_profile_id', $profile->id)
        ->set('subject', 'ثبت از پنل')
        ->set('description', 'توضیح تست')
        ->set('priority', TicketPriority::High->value)
        ->call('create')
        ->assertHasNoErrors();

    expect(Ticket::where('owner_company_id', $company->id)->where('subject', 'ثبت از پنل')->count())->toBe(1);
});

it('lets an operator reply and change status through the Livewire TicketShow component', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $operator = User::factory()->create(['is_super_admin' => false]);
    ticketGiveRole($operator, $company, 'operator');

    $profile = ticketMakeProfile($company, $operator);

    $ticket = app(CreateTicket::class)->handle([
        'owner_company_id' => $company->id,
        'contact_site_profile_id' => $profile->id,
        'subject' => 'تیکت پنل جزئیات',
        'description' => null,
        'priority' => TicketPriority::Normal->value,
    ], $operator);

    $this->actingAs($operator);
    session(['active_company_id' => $company->id]);

    Livewire::test(TicketShow::class, ['ticketId' => $ticket->id])
        ->assertOk()
        ->set('message', 'پاسخ از پنل جزئیات')
        ->call('reply')
        ->assertHasNoErrors()
        ->set('newStatus', TicketStatus::InProgress->value)
        ->call('changeStatus')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress->value);
    expect(TicketReply::where('ticket_id', $ticket->id)->where('message', 'پاسخ از پنل جزئیات')->count())->toBe(1);
});
