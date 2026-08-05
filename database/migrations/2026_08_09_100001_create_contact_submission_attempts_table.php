<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submission_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // snapshot صریح از contact_submissions.owner_company_id (نه BelongsToCompany) —
            // همان الگوی خودِ ContactSubmission، چون شرکت هدف از قبل روی رکورد والد قطعی است.
            $table->uuid('owner_company_id');
            $table->uuid('contact_submission_id');
            // نقش کامل به‌جای user_id خام (بند ۲ DATABASE_CONVENTIONS.md) — کسی که
            // تماس را گرفته، نه صرفاً «سازنده رکورد».
            $table->uuid('attempted_by_user_id');
            // enum PHP در App\Modules\CRM\Enums\ContactAttemptOutcome + CHECK زیر.
            $table->string('outcome', 30);
            $table->string('note', 300)->nullable();
            // زمان کسب‌وکاری تماس، جدا از created_at (مهر ثبت رکورد) — همان الگوی
            // interactions.occurred_at.
            $table->timestamp('attempted_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['contact_submission_id', 'attempted_at'], 'idx_csa_submission_date');
            $table->index('owner_company_id', 'idx_csa_company');

            $table->foreign('contact_submission_id', 'fk_csa_submission')->references('id')->on('contact_submissions');
            $table->foreign('owner_company_id', 'fk_csa_company')->references('id')->on('companies');
            $table->foreign('attempted_by_user_id', 'fk_csa_attempted_by')->references('id')->on('users');
        });

        // لایه دفاعی دوم سطح دیتابیس، طبق الگوی CHECK constraint های موجود —
        // SQLite (محیط تست) این سینتکس را پشتیبانی نمی‌کند.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contact_submission_attempts ADD CONSTRAINT chk_csa_outcome CHECK (outcome IN ('answered_resolved', 'answered_followup_needed', 'no_answer', 'busy', 'wrong_number', 'will_call_back'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submission_attempts');
    }
};
