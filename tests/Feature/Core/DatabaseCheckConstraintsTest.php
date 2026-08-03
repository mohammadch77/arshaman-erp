<?php

use App\Modules\Core\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// این تست فقط روی اتصال mysql معنا دارد چون CHECK CONSTRAINT های migration
// 2026_08_03_000001_fix_datatypes_and_add_checks فقط سینتکس MySQL دارند (نگاه
// کن توضیح داخل آن migration). مجموعه تست پیش‌فرض پروژه روی sqlite در حافظه
// اجرا می‌شود، پس اینجا با skip محافظت شده تا `php artisan test` معمولی نشکند؛
// برای تست واقعی CHECK باید روی یک اتصال mysql (مثلاً از طریق --env=testing با
// دیتابیس تستی جدا طبق بند ۸ CLAUDE.md) اجرا شود.
it('rejects an invalid employment_status via the database CHECK constraint, not just Laravel validation', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $insert = function () {
        DB::table('employees')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => (string) Str::uuid(),
            'full_name' => 'کارمند نامعتبر',
            'national_id' => '1234567890',
            'position' => 'تست',
            'hire_date' => now()->toDateString(),
            // این مقدار در enum سطح PHP هم نامعتبر است، ولی هدف تست این است که
            // خودِ دیتابیس (نه فقط لایه اپلیکیشن) رد کند — پس مستقیم با DB::table
            // که هیچ اعتبارسنجی Laravel روی آن اجرا نمی‌شود insert می‌کنیم.
            'employment_status' => 'invalid',
            'contract_type' => 'project_based',
            'contract_start_date' => now()->toDateString(),
            'base_salary' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});

// سه تست زیر برای migration 2026_08_04_000001_shrink_column_widths_and_add_more_checks
// هستند — طبق درخواست کارفرما، سه ستون CHECK-دار از سه ماژول مختلف (HR، CRM، Core).
// همان محدودیت بالا صدق می‌کند: فقط روی mysql معنا دارند.

it('rejects an invalid employee position via the database CHECK constraint (HR)', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $insert = function () use ($company) {
        DB::table('employees')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => $company->id,
            'full_name' => 'کارمند نامعتبر',
            'national_id' => '1234567891',
            // enum جدید سمت شغلی این مقدار را نمی‌شناسد — نگاه کن
            // app/Modules/HR/Enums/EmployeePosition.php
            'position' => 'invalid_position',
            'hire_date' => now()->toDateString(),
            'employment_status' => 'active',
            'contract_type' => 'project_based',
            'contract_start_date' => now()->toDateString(),
            'base_salary' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});

it('rejects an invalid lead pipeline_stage via the database CHECK constraint (CRM)', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $insert = function () use ($company) {
        DB::table('leads')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => $company->id,
            'source' => 'website',
            'pipeline_stage' => 'invalid_stage',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});

it('rejects an invalid party_type via the database CHECK constraint (Core)', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $insert = function () use ($company) {
        DB::table('parties')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => $company->id,
            'name' => 'طرف‌حساب نامعتبر',
            'party_type' => 'invalid_type',
            // chk_parties_role را هم راضی می‌کند تا فقط علت رد شدن، party_type باشد.
            'is_customer' => 1,
            'is_supplier' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});

it('rejects an invalid product fulfillment_type via the database CHECK constraint (Catalog)', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $insert = function () use ($company) {
        DB::table('products')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => $company->id,
            'name' => 'محصول نامعتبر',
            'sale_price' => 1000,
            'fulfillment_type' => 'invalid_type',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});

// این تست برای chk_parties_role (migration 2026_08_01_100001_create_parties_table)
// است. تست مدل موجود در PartyManagementTest فقط نگهبان Eloquent (Party::booted) را
// چک می‌کند که قبل از رسیدن به دیتابیس صدا می‌زند؛ این‌جا با DB::table (بدون عبور
// از مدل) مستقیم خودِ CHECK دیتابیس را امتحان می‌کنیم.
it('rejects a party with neither is_customer nor is_supplier via the database CHECK constraint, bypassing the Eloquent model guard (Core)', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('CHECK constraint فقط روی MySQL فعال است؛ این تست روی اتصال sqlite پیش‌فرض skip می‌شود.');
    }

    $company = Company::create(['name' => 'آرشامان', 'slug' => 'arshaman', 'business_type' => 'project_services']);

    $insert = function () use ($company) {
        DB::table('parties')->insert([
            'id' => (string) Str::uuid(),
            'owner_company_id' => $company->id,
            'name' => 'بدون نقش خام',
            'party_type' => 'individual',
            'is_customer' => 0,
            'is_supplier' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    expect($insert)->toThrow(QueryException::class);
});
