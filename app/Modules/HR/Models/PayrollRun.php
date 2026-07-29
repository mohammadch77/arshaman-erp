<?php

namespace App\Modules\HR\Models;

use App\Modules\Core\Concerns\BelongsToCompany;
use App\Modules\Core\Models\User;
use App\Modules\HR\Enums\ExpensePostingStatus;
use App\Modules\HR\Enums\PayrollStatus;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    protected $fillable = [
        'owner_company_id',
        'period_month',
        'payroll_status',
        'calculated_at',
        'calculated_by_user_id',
        'finalized_at',
        'finalized_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payroll_status' => PayrollStatus::class,
            'calculated_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PayrollRunFactory
    {
        return PayrollRunFactory::new();
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * قفل مالی — بعد از نهایی‌شدن هیچ فیش این دوره قابل ویرایش/بازمحاسبه نیست.
     */
    public function isLocked(): bool
    {
        return $this->payroll_status->isLocked();
    }

    /**
     * فیش‌هایی از این دوره که هنوز به‌عنوان هزینه در دفتر کل ثبت نشده‌اند.
     *
     * TODO: اتصال به Finance/Expenses — نگاه کن BACKLOG.md #1
     * ماژول هزینه‌ها (فاز ۴) هنوز وجود ندارد؛ این متد فقط نقطه اتصال آینده را
     * علامت می‌زند تا وقتی PostPayrollToExpenses نوشته شد، دوره‌های قبلی HR
     * بدون جست‌وجوی دستی پیدا شوند. هیچ جدول یا ماژول جعلی هزینه ساخته نشده.
     */
    public function pendingExpensePosting(): HasMany
    {
        return $this->payslips()
            ->where('expense_posting_status', ExpensePostingStatus::Pending);
    }
}
