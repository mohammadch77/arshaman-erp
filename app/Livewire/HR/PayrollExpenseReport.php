<?php

namespace App\Livewire\HR;

use App\Modules\Core\Models\Company;
use App\Modules\HR\Enums\PayrollStatus;
use App\Modules\HR\Models\PayrollRun;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

/**
 * گزارش موقت هزینه حقوق ماه، به تفکیک شرکت.
 *
 * موقتی — طبق BACKLOG.md #1، وقتی ماژول هزینه‌ها (فاز ۴) ساخته شد، این عدد باید
 * واقعاً به‌عنوان expense ثبت شود؛ این کامپوننت فقط نمایش است، چیزی نمی‌نویسد.
 *
 * چرا withoutGlobalScopes: این گزارش هلدینگ‌محور است، نه شرکت‌محور — باید همه
 * شرکت‌ها را هم‌زمان کنار هم نشان دهد، نه فقط شرکت فعال سوییچر (بند ۱ CLAUDE.md
 * — «گزارش تجمیعی هلدینگ»). دسترسی با همان PayrollPolicy::viewAny کنترل می‌شود
 * که پنل ادمین حقوق را کنترل می‌کند (super_admin / holding_admin / accountant).
 */
class PayrollExpenseReport extends Component
{
    public ?int $year = null;

    public ?int $month = null;

    public function mount(): void
    {
        $this->authorize('viewAny', PayrollRun::class);

        $now = Jalalian::now();
        $this->year = $now->getYear();
        $this->month = $now->getMonth();
    }

    public function getPeriodMonthProperty(): string
    {
        return sprintf('%04d-%02d', (int) $this->year, (int) $this->month);
    }

    /**
     * یک ردیف به ازای هر شرکتی که برای این ماه یک دوره حقوق نهایی‌شده دارد.
     * دوره‌های draft/calculated عمداً نادیده گرفته می‌شوند — مبلغشان هنوز نهایی نیست.
     */
    public function getRowsProperty(): Collection
    {
        $runs = PayrollRun::withoutGlobalScopes()
            ->where('period_month', $this->periodMonth)
            ->where('payroll_status', PayrollStatus::Finalized)
            ->with(['payslips' => function ($query) {
                $query->withoutGlobalScopes()->with(['employee' => function ($employeeQuery) {
                    $employeeQuery->withoutGlobalScopes();
                }]);
            }])
            ->get();

        $companies = Company::query()
            ->whereIn('id', $runs->pluck('owner_company_id'))
            ->get()
            ->keyBy('id');

        return $runs
            ->map(function (PayrollRun $run) use ($companies) {
                $needingReview = $run->payslips->filter->needsManualReview();

                return [
                    'company' => $companies->get($run->owner_company_id),
                    'total_net' => Money::round($run->payslips->reduce(
                        fn (string $carry, $payslip) => Money::add($carry, (string) $payslip->net_amount),
                        '0'
                    )),
                    'payslip_count' => $run->payslips->count(),
                    'review_count' => $needingReview->count(),
                    'review_names' => $needingReview->map(fn ($payslip) => $payslip->employee?->full_name)->filter()->values(),
                ];
            })
            ->sortBy(fn (array $row) => $row['company']?->name)
            ->values();
    }

    public function getGrandTotalProperty(): string
    {
        return Money::round($this->rows->reduce(
            fn (string $carry, array $row) => Money::add($carry, $row['total_net']),
            '0'
        ));
    }

    public function getTotalReviewCountProperty(): int
    {
        return (int) $this->rows->sum('review_count');
    }

    public function render()
    {
        return view('livewire.hr.payroll-expense-report', [
            'rows' => $this->rows,
        ]);
    }
}
