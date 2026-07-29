<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\HR\Enums\ExpensePostingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Payslip extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'owner_company_id',
        'gross_salary_amount',
        'overtime_amount',
        'absence_deduction_amount',
        'unpaid_leave_deduction_amount',
        'insurance_amount',
        'tax_amount',
        'benefits_amount',
        'net_amount',
        'raw_net_amount',
        'currency_id',
        'expense_posting_status',
    ];

    protected function casts(): array
    {
        return [
            // هرگز float — CLAUDE.md بند ۳
            'gross_salary_amount' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'absence_deduction_amount' => 'decimal:2',
            'unpaid_leave_deduction_amount' => 'decimal:2',
            'insurance_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'benefits_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'raw_net_amount' => 'decimal:2',
            'expense_posting_status' => ExpensePostingStatus::class,
        ];
    }

    /**
     * قفل مالی در سطح مدل.
     *
     * چرا اینجا و نه فقط در Action: طبق CLAUDE.md بند ۹، Action تنها caller نیست.
     * اگر قفل فقط داخل CalculatePayroll باشد، هر مسیر دیگری (کنسول، job، کد آینده،
     * یک کامپوننت Livewire جدید) می‌تواند فیش یک دوره نهایی‌شده را مستقیم عوض کند.
     * این نگهبان همان تضمین بند ۵.۵ («سند posted غیرقابل ویرایش است») را در سطح
     * مدل می‌بندد، مستقل از اینکه از کجا صدا زده شده.
     */
    protected static function booted(): void
    {
        $guard = function (Payslip $payslip): void {
            $run = $payslip->payrollRun()->withoutGlobalScopes()->first();

            if ($run?->isLocked()) {
                throw ValidationException::withMessages([
                    'payroll_run_id' => 'این دوره حقوق نهایی شده است و فیش‌های آن قابل تغییر نیستند.',
                ]);
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * آیا خالص این فیش در صفر clamp شده است؟
     *
     * پرشدن raw_net_amount یعنی کسورات (غیبت، مرخصی بدون‌حقوق، بیمه، مالیات) از
     * مجموع حقوق و مزایا بیشتر شده. عدد قابل پرداخت صفر است، ولی خود فیش باید
     * دست حسابدار بررسی شود — نه اینکه بی‌صدا صفر بماند.
     */
    public function needsManualReview(): bool
    {
        return $this->raw_net_amount !== null;
    }

    /**
     * جمع کسورات — فقط برای نمایش در فیش، نه مبنای محاسبه.
     */
    public function totalDeductions(): string
    {
        return bcadd(
            bcadd((string) $this->absence_deduction_amount, (string) $this->unpaid_leave_deduction_amount, 2),
            bcadd((string) $this->insurance_amount, (string) $this->tax_amount, 2),
            2
        );
    }
}
