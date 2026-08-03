<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // nullable عمدی: بدون نقطه سفارش تعریف‌شده یعنی هشدار «زیر نقطه سفارش»
            // برای این محصول بی‌معناست، نه اینکه صفر فرض شود (همان الگوی cost_price).
            $table->integer('reorder_point')->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reorder_point');
        });
    }
};
