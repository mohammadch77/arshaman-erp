<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            // دلیل رد، جدا از reason (که دلیل خودِ درخواست کارمند است).
            // دو نقش متفاوت، پس دو ستون — نه بازنویسی reason.
            // nullable چون رد بدون توضیح هم مجاز است.
            $table->text('rejection_reason')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
