<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * هر ردیف attendances از این پس یک «تردد» است، نه یک روز.
 *
 * کارمند می‌تواند در یک روز چندین بار ورود/خروج بزند (رفتن برای کار شخصی و
 * برگشتن)، پس چند ردیف برای یک کارمند/روز مجاز است.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ۱. ایندکس جایگزین **قبل** از حذف یکتا ساخته می‌شود.
        //
        //    ترتیب اهمیت دارد: کلید خارجی fk_attendance_employee به یک ایندکس که
        //    با employee_id شروع شود نیاز دارد و MySQL تا وقتی جایگزینی نباشد،
        //    اجازه حذف uq_attendance_employee_date را نمی‌دهد
        //    («Cannot drop index: needed in a foreign key constraint»).
        //    SQLite این قید را اعمال نمی‌کند، پس ترتیب اشتباه فقط روی MySQL
        //    شکست می‌خورد — دقیقاً همان‌جایی که داده واقعی هست.
        //
        //    هر گام با has* محافظت شده تا اگر migration وسط کار شکست بخورد
        //    (مثلاً چون داده موجود قید یکتای گام آخر را نقض می‌کند)، اجرای
        //    دوباره‌اش روی گام‌های انجام‌شده نخورد.
        if (! Schema::hasIndex('attendances', 'idx_attendance_employee_open')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['employee_id', 'check_out_at'], 'idx_attendance_employee_open');
            });
        }

        // ۲. یکتایی «یک ردیف در روز» برداشته می‌شود — دقیقاً همان چیزی که
        //    مانع تردد چندباره بود.
        if (Schema::hasIndex('attendances', 'uq_attendance_employee_date')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropUnique('uq_attendance_employee_date');
            });
        }

        // ۳. کسری و اضافه‌کاری از ردیف حذف می‌شوند.
        //
        //    این دو مفهوم **روزانه** هستند، نه ردیفی: با دو تردد چهارساعته در یک
        //    روز، هر ردیف جدا با ۴۸۰ دقیقه مقایسه می‌شد و هرکدام ۲۴۰ دقیقه کسری
        //    می‌گرفت — جمعاً ۴۸۰ دقیقه کسری برای روزی که کامل کار شده. نگه‌داشتن
        //    این ستون‌ها روی ردیف یعنی داده‌ای که همیشه غلط است.
        //
        //    از این پس در لحظه از AttendanceCalculator محاسبه و فقط در
        //    monthly_attendance_summaries (که از قبل روزانه/ماهانه است) ذخیره می‌شوند.
        if (Schema::hasColumn('attendances', 'late_minutes')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn(['late_minutes', 'overtime_minutes']);
            });
        }

        // ۴. تضمین سطح دیتابیس: هر کارمند حداکثر **یک** تردد باز دارد.
        //
        //    MySQL 8 ایندکس یکتای شرطی ندارد، ولی در ایندکس یکتا مقادیر NULL با
        //    هم برخورد نمی‌کنند. پس یک ستون تولیدشده که فقط برای ردیف‌های باز
        //    مقدار می‌گیرد، دقیقاً همان اثر را می‌دهد: فقط ردیف‌های باز یکتایی
        //    می‌گیرند و ردیف‌های بسته آزادند.
        //
        //    چرا لازم است: بدون آن، دو کلیک سریع روی «ثبت ورود» می‌تواند دو ردیف
        //    باز بسازد و گارد اپلیکیشن در شرایط مسابقه دور زده شود.
        //    بند ۳ CLAUDE.md: «هم validation هم constraint سطح دیتابیس».
        //
        //    virtualAs و نه storedAs: SQLite (دیتابیس تست) اجازه افزودن ستون
        //    تولیدشده STORED به جدول موجود را نمی‌دهد، ولی VIRTUAL را می‌دهد.
        $this->addOpenPunchMarker();
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('uq_attendance_single_open_punch');
            $table->dropColumn('open_punch_marker');
            $table->dropIndex('idx_attendance_employee_open');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->integer('late_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->unique(['employee_id', 'attendance_date'], 'uq_attendance_employee_date');
        });
    }

    protected function addOpenPunchMarker(): void
    {
        $expression = 'case when check_out_at is null then 1 else null end';

        if (! Schema::hasColumn('attendances', 'open_punch_marker')) {
            Schema::table('attendances', function (Blueprint $table) use ($expression) {
                $table->integer('open_punch_marker')->virtualAs($expression)->nullable();
            });
        }

        if (Schema::hasIndex('attendances', 'uq_attendance_single_open_punch')) {
            return;
        }

        // اگر داده موجود از قبل بیش از یک تردد باز برای یک کارمند داشته باشد،
        // ساخت این ایندکس شکست می‌خورد. پیام پیش‌فرض MySQL فقط یک شناسه تکراری
        // می‌دهد؛ این بررسی می‌گوید دقیقاً کدام کارمند و باید چه‌کار کرد.
        $offenders = DB::table('attendances')
            ->whereNull('check_out_at')
            ->select('employee_id')
            ->groupBy('employee_id')
            ->havingRaw('count(*) > 1')
            ->pluck('employee_id');

        if ($offenders->isNotEmpty()) {
            throw new RuntimeException(
                'این کارمندان بیش از یک تردد باز دارند و تا اصلاح دستی، ایندکس یکتا ساخته نمی‌شود: '
                .$offenders->implode('، ')
                .' — ترددهای باز اضافی را ببندید یا حذف کنید، سپس دوباره migrate بزنید.'
            );
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['employee_id', 'open_punch_marker'], 'uq_attendance_single_open_punch');
        });
    }
};
