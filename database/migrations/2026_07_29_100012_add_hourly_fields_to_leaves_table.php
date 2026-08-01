<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // فقط برای leave_type = hourly پر می‌شوند؛ برای مرخصی روزانه NULL می‌مانند.
            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');

            // ساعت‌های مرخصی ساعتی. عمداً ستون جدا از days_count است و نه کسر
            // اعشاری از روز:
            //
            // ۱. days_count و monthly_attendance_summaries.total_leave_days هر دو
            //    INT اند و از قبل در گزارش Session 4 مصرف‌کننده دارند؛ اعشاری‌کردنشان
            //    معنای ستون‌های موجود را عوض می‌کرد.
            // ۲. کسر اعشاری روز بعداً ضرب در نرخ روزانه می‌شد — همان ابهام گردکردنی
            //    که بند ۳ CLAUDE.md از آن پرهیز می‌دهد.
            // ۳. نرخ ساعتی از قبل وجود دارد و دقیقاً سازگار است:
            //    base/176 = base/(22×8). یعنی ۸ ساعت مرخصی ساعتی = یک روز، بدون
            //    هیچ ضریب تبدیل اضافه.
            $table->decimal('hours_count', 5, 2)->nullable()->after('days_count');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'hours_count']);
        });
    }
};
