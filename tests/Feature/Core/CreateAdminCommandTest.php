<?php

use App\Modules\Core\Models\User;

it('creates a super admin', function () {
    $this->artisan('erp:create-admin')
        ->expectsQuestion('نام کامل', 'مدیر کل')
        ->expectsQuestion('ایمیل', 'admin@arshaman.local')
        ->expectsQuestion('رمز عبور (حداقل ۸ کاراکتر)', 'SecretPass123')
        ->expectsQuestion('تکرار رمز عبور', 'SecretPass123')
        ->assertExitCode(0);

    $admin = User::where('email', 'admin@arshaman.local')->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_super_admin)->toBeTrue();
});
