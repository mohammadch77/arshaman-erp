<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // تا Session 6 این ستون لازم نبود چون رکورد حضور عملاً ویرایش نمی‌شد.
            // با افزوده‌شدن ویرایش (هم از پنل کارمند، هم از پنل ادمین) «چه کسی
            // آخرین بار این رکورد را عوض کرد» یک سؤال واقعی شد.
            //
            // recorded_by معنای متفاوتی دارد و عمداً دست‌نخورده می‌ماند: همان
            // «چه کسی اولین بار ثبت کرد» (self یا admin). اگر با هر ویرایش
            // بازنویسی می‌شد، دیگر معلوم نبود رکورد اصالتاً self بوده یا نه.
            $table->uuid('updated_by_user_id')->nullable()->after('created_by_user_id');

            $table->foreign('updated_by_user_id', 'fk_attendance_updated_by')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign('fk_attendance_updated_by');
            $table->dropColumn('updated_by_user_id');
        });
    }
};
