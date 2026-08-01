<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // کارمند از این به بعد می‌تواند درخواست خودش را (تا قبل از تأیید/رد)
            // حذف کند. طبق بند ۳ CLAUDE.md حذف همیشه نرم است — یک درخواست
            // مرخصیِ حذف‌شده هم بخشی از تاریخچه است و نباید فیزیکی ناپدید شود.
            $table->softDeletes();

            // «چه کسی آخرین بار این درخواست را عوض کرد» — با افزوده‌شدن ویرایش
            // توسط خودِ کارمند، این سؤال واقعی شد. بند ۳ CLAUDE.md.
            $table->uuid('updated_by_user_id')->nullable()->after('created_by_user_id');

            $table->foreign('updated_by_user_id', 'fk_leaves_updated_by')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign('fk_leaves_updated_by');
            $table->dropColumn(['updated_by_user_id', 'deleted_at']);
        });
    }
};
