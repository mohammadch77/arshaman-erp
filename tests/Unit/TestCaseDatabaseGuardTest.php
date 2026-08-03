<?php

use Tests\TestCase;

// نگاه کن CLAUDE.md بند ۹.۱ — این تست ثابت می‌کند گارد داخل Tests\TestCase واقعاً
// جلوی اجرای RefreshDatabase روی دیتابیس واقعی arshaman_erp را می‌گیرد، نه اینکه
// فقط یک قرارداد مستندشده باشد. عمداً از `new class(...) extends TestCase` استفاده
// شده (نه Livewire::test یا اجرای واقعی یک تست Feature) چون هدف این است که فقط
// متد گارد را مستقیم صدا بزنیم — بدون اینکه lifecycle واقعی PHPUnit (که setUp را
// صدا می‌زند و RefreshDatabase را واقعاً اجرا می‌کند) هرگز فعال شود. یعنی این تست
// خودش هرگز به دیتابیس واقعی وصل نمی‌شود، فقط رفتار گارد را شبیه‌سازی می‌کند.
it('stops immediately with a clear exception instead of letting RefreshDatabase touch the real arshaman_erp database', function () {
    $originalConnection = getenv('DB_CONNECTION');
    $originalDatabase = getenv('DB_DATABASE');

    putenv('DB_CONNECTION=mysql');
    putenv('DB_DATABASE=arshaman_erp');
    $_ENV['DB_CONNECTION'] = 'mysql';
    $_ENV['DB_DATABASE'] = 'arshaman_erp';

    try {
        $probe = new class('guard_probe') extends TestCase
        {
            public function callGuard(): void
            {
                $method = new ReflectionMethod($this, 'guardAgainstRunningTestsOnRealDatabase');
                $method->setAccessible(true);
                $method->invoke($this);
            }
        };

        expect(fn () => $probe->callGuard())
            ->toThrow(RuntimeException::class, "دیتابیس واقعی 'arshaman_erp'");
    } finally {
        restoreEnv('DB_CONNECTION', $originalConnection);
        restoreEnv('DB_DATABASE', $originalDatabase);
    }
});

it('does not throw when the test database is the safe sqlite target', function () {
    $originalConnection = getenv('DB_CONNECTION');
    $originalDatabase = getenv('DB_DATABASE');

    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE=:memory:');
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['DB_DATABASE'] = ':memory:';

    try {
        $probe = new class('guard_probe') extends TestCase
        {
            public function callGuard(): void
            {
                $method = new ReflectionMethod($this, 'guardAgainstRunningTestsOnRealDatabase');
                $method->setAccessible(true);
                $method->invoke($this);
            }
        };

        $probe->callGuard();
    } finally {
        restoreEnv('DB_CONNECTION', $originalConnection);
        restoreEnv('DB_DATABASE', $originalDatabase);
    }

    expect(true)->toBeTrue();
});

function restoreEnv(string $name, string|false $originalValue): void
{
    if ($originalValue === false) {
        putenv($name);
        unset($_ENV[$name]);

        return;
    }

    putenv("{$name}={$originalValue}");
    $_ENV[$name] = $originalValue;
}
