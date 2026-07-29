<?php

use App\Modules\Core\Models\Company;
use App\Modules\HR\Models\Holiday;
use App\Modules\HR\Services\WorkCalendar;
use Carbon\Carbon;

it('treats friday as a non-workday', function () {
    $friday = Carbon::parse('2026-07-31'); // جمعه

    expect((new WorkCalendar)->isWorkday($friday))->toBeFalse();
});

it('treats a global official holiday as a non-workday', function () {
    Holiday::create([
        'owner_company_id' => null,
        'title' => 'تعطیل سراسری تست',
        'holiday_date' => '2026-08-01',
        'is_recurring_yearly' => false,
    ]);

    expect((new WorkCalendar)->isWorkday(Carbon::parse('2026-08-01')))->toBeFalse();
});

it('treats a recurring yearly holiday as a non-workday every year', function () {
    Holiday::create([
        'owner_company_id' => null,
        'title' => 'نوروز',
        'holiday_date' => '2026-03-21',
        'is_recurring_yearly' => true,
    ]);

    expect((new WorkCalendar)->isWorkday(Carbon::parse('2027-03-21')))->toBeFalse();
});

it('treats an ordinary day as a workday', function () {
    $ordinaryDay = Carbon::parse('2026-08-02'); // یکشنبه، بدون تعطیلی

    expect((new WorkCalendar)->isWorkday($ordinaryDay))->toBeTrue();
});

it('does not let a company-specific holiday affect another company', function () {
    $companyA = Company::create(['name' => 'شرکت الف', 'slug' => 'company-a', 'business_type' => 'project_services']);
    $companyB = Company::create(['name' => 'شرکت ب', 'slug' => 'company-b', 'business_type' => 'project_services']);

    Holiday::create([
        'owner_company_id' => $companyA->id,
        'title' => 'تعطیلی مخصوص شرکت الف',
        'holiday_date' => '2026-08-02',
        'is_recurring_yearly' => false,
    ]);

    $date = Carbon::parse('2026-08-02');

    expect((new WorkCalendar)->isWorkday($date, $companyA->id))->toBeFalse()
        ->and((new WorkCalendar)->isWorkday($date, $companyB->id))->toBeTrue()
        ->and((new WorkCalendar)->isWorkday($date))->toBeTrue();
});
