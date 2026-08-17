<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دو ستون جدید مستقل:
     * - step_form_fields: فرم اضافه‌ی اختیاری خودِ یک مرحله‌ی approval (بخش ۳
     *   Session جاری) — همان ساختار request_form_fields سطح تعریف، فقط سطح
     *   مرحله. nullable یعنی این مرحله فرم اضافه ندارد (اکثر مراحل).
     * - display_order: فقط برای حفظ ترتیب نمایش اصلی مراحل هنگام بازگشایی
     *   فرم ویرایش (بخش ۶) — هیچ اثری روی منطق اجرای واقعی ProcessEngine ندارد.
     */
    public function up(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->json('step_form_fields')->nullable()->after('condition_value');
            $table->unsignedSmallInteger('display_order')->default(0)->after('step_form_fields');
        });
    }

    public function down(): void
    {
        Schema::table('process_steps', function (Blueprint $table) {
            $table->dropColumn(['step_form_fields', 'display_order']);
        });
    }
};
