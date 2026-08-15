<?php

use App\Livewire\CRM\Public\CustomerSignupForm as PublicCustomerSignupForm;
use App\Modules\Core\Models\Company;
use App\Modules\CRM\Actions\CaptureCustomerSignup;
use App\Modules\CRM\Models\ContactSubmission;
use App\Modules\SiteBuilder\Enums\WidgetKey;
use App\Modules\SiteBuilder\Services\DynamicWidgetResolver;
use App\Modules\SiteBuilder\Services\WidgetContentRenderer;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('lets a guest sign up through the public customer-signup-form component', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    Livewire::test(PublicCustomerSignupForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'مشتری تازه')
        ->set('phone', '09121234567')
        ->set('email', 'signup@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $submission = ContactSubmission::first();

    expect($submission)->not->toBeNull();
    expect($submission->owner_company_id)->toBe($company->id);
    expect($submission->full_name)->toBe('مشتری تازه');
    expect($submission->source)->toBe('site_signup');
    expect(auth()->check())->toBeFalse();
});

it('rejects an invalid phone number at the validation layer for signup', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    Livewire::test(PublicCustomerSignupForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'مشتری تازه')
        ->set('phone', '12345')
        ->call('submit')
        ->assertHasErrors(['phone']);

    expect(ContactSubmission::count())->toBe(0);
});

it('silently drops a signup when the honeypot field is filled, without any error', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    Livewire::test(PublicCustomerSignupForm::class, ['companySlug' => $company->slug])
        ->set('full_name', 'ربات')
        ->set('phone', '09121234567')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    expect(ContactSubmission::count())->toBe(0);
});

it('rejects signups from the same IP after the rate limit window', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    for ($i = 0; $i < CaptureCustomerSignup::MAX_ATTEMPTS_PER_WINDOW; $i++) {
        app(CaptureCustomerSignup::class)->handle($company, [
            'full_name' => 'مشتری '.$i,
            'phone' => '09121234567',
            'email' => null,
            'honeypot' => '',
        ], '10.0.0.1');
    }

    expect(fn () => app(CaptureCustomerSignup::class)->handle($company, [
        'full_name' => 'مشتری اضافه',
        'phone' => '09121234567',
        'email' => null,
        'honeypot' => '',
    ], '10.0.0.1'))->toThrow(ThrottleRequestsException::class);

    RateLimiter::clear('customer-signup:10.0.0.1:'.$company->id);
});

it('keeps signups isolated between companies', function () {
    $companyA = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'ورایفکس', 'slug' => 'verifex', 'business_type' => 'hybrid']);

    app(CaptureCustomerSignup::class)->handle($companyA, [
        'full_name' => 'مشتری آ',
        'phone' => '09121234567',
        'email' => null,
        'honeypot' => '',
    ], '10.0.0.2');

    expect(ContactSubmission::where('owner_company_id', $companyA->id)->count())->toBe(1);
    expect(ContactSubmission::where('owner_company_id', $companyB->id)->count())->toBe(0);
});

it('rejects an invalid source value at the database CHECK constraint layer', function () {
    if (Schema::getConnection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL واقعی اعمال می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    expect(fn () => ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
        'source' => 'not_a_real_source',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('defaults source to contact_form for a plain ContactSubmission insert', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $submission = ContactSubmission::create([
        'owner_company_id' => $company->id,
        'full_name' => 'مشتری تست',
        'phone' => '09121234567',
        'email' => null,
        'subject' => null,
        'message' => 'پیام تست',
    ]);

    expect($submission->refresh()->source)->toBe('contact_form');
});

it('renders the customer_signup_form widget as a placeholder marker in the admin/preview render path', function () {
    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'signup-1', 'widget_key' => WidgetKey::CustomerSignupForm->value, 'values' => ['section_title' => 'عضویت'], 'children' => []],
    ]);

    expect($html)->toContain('<!--sb:customer_signup_form:');
    expect($html)->toContain('فرم ثبت‌نام مشتری واقعی اینجا نمایش داده می‌شود');
    expect($html)->not->toContain('wire:id');
});

it('resolves the customer_signup_form marker into a real hydrated Livewire component on the public route', function () {
    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'signup-1', 'widget_key' => WidgetKey::CustomerSignupForm->value, 'values' => ['section_title' => 'عضویت'], 'children' => []],
    ]);

    $resolved = app(DynamicWidgetResolver::class)->resolve($html, $company);

    expect($resolved)->not->toContain('<!--sb:customer_signup_form:');
    expect($resolved)->toContain('wire:id');
    expect($resolved)->toContain('عضویت');
});

it('escapes an XSS payload in the customer_signup_form section_title and cannot break out of the marker comment', function () {
    $malicious = 'عنوان--><script>alert(1)</script>';

    $html = app(WidgetContentRenderer::class)->render([
        ['id' => 'signup-1', 'widget_key' => WidgetKey::CustomerSignupForm->value, 'values' => ['section_title' => $malicious], 'children' => []],
    ]);

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);
    $resolved = app(DynamicWidgetResolver::class)->resolve($html, $company);

    expect($resolved)->not->toContain('<script>alert(1)</script>');
});
