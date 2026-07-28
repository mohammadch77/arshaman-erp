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

    public function mount(CompanyContext $context): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->isSuperAdmin = $user->is_super_admin;
        $this->activeCompanyId = $context->id();
        $this->isAggregateView = $context->isAggregateView();
    }

    public function getCompaniesProperty(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

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
