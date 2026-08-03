<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * نام دیتابیس(های) واقعی dev/production که هیچ‌وقت نباید هدف تست باشند —
     * RefreshDatabase این دیتابیس را کاملاً drop/migrate می‌کند (نگاه کن CLAUDE.md بند ۸.۱).
     *
     * @var list<string>
     */
    private const FORBIDDEN_DATABASES = ['arshaman_erp'];

    /**
     * گارد صریح، قبل از هر چیز دیگری در setUp اجرا می‌شود — یعنی قبل از اینکه
     * parent::setUp() اپلیکیشن را بسازد و RefreshDatabase (اگر روی این تست فعال
     * باشد) فرصت کند دیتابیس را drop/migrate کند. عمداً از env() خام استفاده شده،
     * نه config()، چون در این نقطه هنوز اپلیکیشن Laravel boot نشده.
     */
    protected function setUp(): void
    {
        $this->guardAgainstRunningTestsOnRealDatabase();

        parent::setUp();
    }

    private function guardAgainstRunningTestsOnRealDatabase(): void
    {
        $connection = env('DB_CONNECTION');
        $database = env('DB_DATABASE');

        if ($connection === 'mysql' && in_array($database, self::FORBIDDEN_DATABASES, true)) {
            throw new RuntimeException(
                "متوقف شد: تست‌ها می‌خواستند روی دیتابیس واقعی '{$database}' اجرا شوند. ".
                'RefreshDatabase این دیتابیس را کاملاً پاک و بازسازی می‌کند — دقیقاً همان '.
                'حادثه‌ای که این گارد برایش ساخته شد. اگر واقعاً نیاز به تست روی MySQL '.
                "واقعی داری (نه sqlite)، یک دیتابیس جدا و صریح مثل '{$database}_testing' بساز ".
                'و DB_DATABASE را روی همان بگذار، هرگز روی خودِ '."'{$database}'."
            );
        }
    }
}
