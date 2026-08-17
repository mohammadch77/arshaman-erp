<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * فقط برای حفظ ترتیب نمایش اصلی گذارها هنگام بازگشایی فرم ویرایش (بخش ۶
     * Session جاری) — هیچ اثری روی ProcessEngine::moveFrom() ندارد که همیشه
     * بر پایه‌ی from_step_id/on_result کوئری می‌زند، نه ترتیب.
     */
    public function up(): void
    {
        Schema::table('process_transitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('display_order')->default(0)->after('on_result');
        });
    }

    public function down(): void
    {
        Schema::table('process_transitions', function (Blueprint $table) {
            $table->dropColumn('display_order');
        });
    }
};
