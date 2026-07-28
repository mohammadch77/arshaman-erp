<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('module', 50);
            $table->string('display_name', 200);
            $table->timestamp('created_at')->nullable();

            $table->unique('name', 'uq_permissions_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
