<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            // مبلغ خام محاسبه‌شده، فقط وقتی منفی درآمد.
            //
            // چرا هر دو ستون: net_amount مبلغ قابل پرداخت است و منفی‌بودنش
            // بی‌معناست (کارمند به شرکت بدهکار نمی‌شود)، پس در صفر clamp می‌شود.
            // ولی خودِ عدد منفی یک سیگنال واقعی است — یعنی کسورات از حقوق بیشتر
            // شده — و اگر دور ریخته شود، حسابدار هیچ‌وقت نمی‌فهمد کدام فیش نیاز
            // به بررسی دستی دارد. پس عدد خام اینجا می‌ماند.
            //
            // NULL = هیچ clamp ای رخ نداده و net_amount همان محاسبه واقعی است.
            $table->decimal('raw_net_amount', 18, 2)->nullable()->after('net_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn('raw_net_amount');
        });
    }
};
