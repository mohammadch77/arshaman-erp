<?php

namespace App\Livewire\HR;

use App\Modules\Core\Services\CompanyContext;
use App\Modules\HR\Actions\CreateEmployee;
use App\Modules\HR\Actions\TerminateEmployee;
use App\Modules\HR\Actions\UpdateEmployee;
use App\Modules\HR\Enums\ContractType;
use App\Modules\HR\Models\Employee;
use App\Support\Jalali;
use Livewire\Component;
use Mary\Traits\Toast;

class EmployeeForm extends Component
{
    use Toast;

    public ?Employee $record = null;

    public string $full_name = '';

    public string $national_id = '';

    public string $phone = '';

    public string $address = '';

    public string $position = '';

    public string $hire_date = '';

    public string $contract_type = '';

    public string $contract_start_date = '';

    public string $contract_end_date = '';

    public string $base_salary = '';

    public string $terminationDate = '';

    /**
     * تاریخ‌ها همیشه از طریق سه انتخاب‌گر شمسی (سال/ماه/روز) وارد می‌شوند —
     * طبق بخش ۳ CLAUDE.md («ذخیره UTC، نمایش و ورودی شمسی»)، نه تقویم میلادی مرورگر.
     * کلیدهای این آرایه دقیقاً هم‌نام پراپرتی‌های میلادی بالا هستند که برای
     * اعتبارسنجی/ذخیره استفاده می‌شوند؛ این آرایه فقط لایه ورودی است.
     *
     * @var array<string, array{year: ?int, month: ?int, day: ?int}>
     */
    public array $jalaliParts = [
        'hire_date' => ['year' => null, 'month' => null, 'day' => null],
        'contract_start_date' => ['year' => null, 'month' => null, 'day' => null],
        'contract_end_date' => ['year' => null, 'month' => null, 'day' => null],
        'terminationDate' => ['year' => null, 'month' => null, 'day' => null],
    ];

    public function mount(?string $employee = null): void
    {
        if ($employee) {
            $this->record = Employee::findOrFail($employee);
            $this->authorize('update', $this->record);

            $this->full_name = $this->record->full_name;
            $this->national_id = $this->record->national_id;
            $this->phone = (string) $this->record->phone;
            $this->address = (string) $this->record->address;
            $this->position = $this->record->position;
            $this->hire_date = $this->record->hire_date->toDateString();
            $this->contract_type = $this->record->contract_type->value;
            $this->contract_start_date = $this->record->contract_start_date->toDateString();
            $this->contract_end_date = $this->record->contract_end_date?->toDateString() ?? '';
            $this->base_salary = (string) $this->record->base_salary;

            $this->jalaliParts['hire_date'] = Jalali::toJalaliParts($this->hire_date);
            $this->jalaliParts['contract_start_date'] = Jalali::toJalaliParts($this->contract_start_date);
            $this->jalaliParts['contract_end_date'] = Jalali::toJalaliParts($this->contract_end_date ?: null);

            return;
        }

        $this->authorize('create', Employee::class);
    }

    /**
     * وقتی یکی از سه انتخاب‌گر شمسی تغییر کند، پراپرتی میلادی متناظر
     * (که برای اعتبارسنجی/ذخیره استفاده می‌شود) دوباره محاسبه می‌شود.
     */
    public function updatedJalaliParts($value, $key): void
    {
        [$field] = explode('.', $key);

        if (! property_exists($this, $field)) {
            return;
        }

        $year = $this->jalaliParts[$field]['year'] ?? null;
        $month = $this->jalaliParts[$field]['month'] ?? null;
        $day = $this->jalaliParts[$field]['day'] ?? null;

        // اگر با تغییر ماه/سال، روز قبلاً انتخاب‌شده دیگر معتبر نباشد (مثلاً ۳۱ برای مهر)،
        // به آخرین روز معتبر همان ماه کلمپ می‌شود — هم در select هم در تاریخ ذخیره‌شده.
        if ($day && $month) {
            $maxDay = Jalali::maxDayForMonth($year, $month);

            if ((int) $day > $maxDay) {
                $day = $maxDay;
                $this->jalaliParts[$field]['day'] = $maxDay;
            }
        }

        $this->{$field} = Jalali::toGregorian($year, $month, $day) ?? '';
    }

    public function getContractTypeOptionsProperty(): array
    {
        return array_map(fn (ContractType $case) => ['id' => $case->value, 'name' => $case->label()], ContractType::cases());
    }

    public function getIsContractExpiringSoonProperty(): bool
    {
        if ($this->contract_end_date === '') {
            return false;
        }

        // همان قاعده مدل: مقایسه روز-با-روز به وقت محلی، نه UTC.
        $today = Jalali::today();

        return Jalali::calendarDay($this->contract_end_date)
            ->between($today, $today->copy()->addDays(30));
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'national_id' => ['required', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'position' => ['required', 'string', 'max:150'],
            'hire_date' => ['required', 'date'],
            'contract_type' => ['required', 'string'],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after:contract_start_date'],
            'base_salary' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function save(CreateEmployee $createAction, UpdateEmployee $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();
        $data['contract_end_date'] = $data['contract_end_date'] ?: null;
        $data['phone'] = $data['phone'] ?: null;
        $data['address'] = $data['address'] ?: null;

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('اطلاعات کارمند به‌روزرسانی شد.', redirectTo: route('employees.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('کارمند جدید ساخته شد.', redirectTo: route('employees.index'));
    }

    public function terminate(TerminateEmployee $action): void
    {
        $this->validate([
            'terminationDate' => ['required', 'date'],
        ]);

        $action->handle($this->record, $this->terminationDate, auth()->user());

        $this->success('پایان همکاری ثبت شد.', redirectTo: route('employees.index'));
    }

    public function render()
    {
        return view('livewire.hr.employee-form');
    }
}
