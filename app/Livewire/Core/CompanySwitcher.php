<?php

namespace App\Livewire\Core;

use App\Modules\Core\Models\Company;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use Illuminate\Support\Collection;
use Livewire\Component;

class CompanySwitcher extends Component
{
    public ?string $activeCompanyId = null;

    public bool $isAggregateView = false;

    public bool $isSuperAdmin = false;

    /**
     * بدون کاربر لاگین‌شده، سوییچر چیزی برای نشان‌دادن ندارد و خالی رندر می‌شود
     * — نه اینکه با خطای ۵۰۰ کل صفحه را پایین بیاورد.
     *
     * این فقط محافظت از یک مسیر نیست: سوییچر در پوسته پیشخوان روی **هر** صفحه
     * رندر می‌شود، پس اگر session بین بارگذاری صفحه و درخواست بعدی Livewire
     * منقضی شود، این کامپوننت اولین چیزی است که به کاربر null می‌رسد. تبدیل آن
     * از خطای کشنده به یک رندر خالی، کاربر را به مسیر عادی ورود می‌رساند.
     */
    public function mount(CompanyContext $context): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->isSuperAdmin = $user->is_super_admin;
        $this->activeCompanyId = $context->id();
        $this->isAggregateView = $context->isAggregateView();
    }

    public function getCompaniesProperty(): Collection
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if ($this->isSuperAdmin) {
            return Company::query()->where('is_active', true)->orderBy('name')->get();
        }

        return Company::query()
            ->whereIn('id', $user->companyRoles()->pluck('owner_company_id'))
            ->orderBy('name')
            ->get();
    }

    public function switchTo(string $companyId, CompanyContext $context): void
    {
        $context->set($companyId);

        $this->redirect(request()->header('referer') ?: route('home'), navigate: true);
    }

    public function switchToAggregate(CompanyContext $context): void
    {
        $context->setAggregate();

        $this->redirect(request()->header('referer') ?: route('home'), navigate: true);
    }

    public function render()
    {
        return view('livewire.core.company-switcher');
    }
}
