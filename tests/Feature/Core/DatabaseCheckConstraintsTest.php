<?php

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
