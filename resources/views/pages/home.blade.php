<?php

use App\Modules\Core\Models\UserCompanyRole;
use App\Modules\Core\Services\CompanyContext;
use Livewire\Component;

new class extends Component {
    public ?string $companyName = null;

    public ?string $roleName = null;

    public bool $isAggregateView = false;

    public function mount(CompanyContext $context): void
    {
        $this->isAggregateView = $context->isAggregateView();

        $activeCompany = $context->activeCompany();
        $this->companyName = $activeCompany?->name;

        if ($activeCompany) {
            $this->roleName = auth()->user()->is_super_admin
                ? 'ادمین کل'
                : UserCompanyRole::query()
                    ->where('user_id', auth()->id())
                    ->where('owner_company_id', $activeCompany->id)
                    ->with('role')
                    ->first()?->role?->display_name;
        }
    }
}; ?>

<div>
    <x-header title="پیشخوان" subtitle="ماژول Core — Session 3" separator />

    <x-card shadow>
        <div class="flex flex-col gap-2">
            <div>خوش آمدید، <span class="font-bold">{{ auth()->user()->full_name }}</span>.</div>

            @if($isAggregateView)
                <div>نمای فعال: <span class="font-bold text-primary">نمای تجمیعی هلدینگ</span></div>
            @else
                <div>شرکت فعال: <span class="font-bold text-primary">{{ $companyName ?? 'نامشخص' }}</span></div>
                <div>نقش شما: <span class="font-bold">{{ $roleName ?? 'بدون نقش' }}</span></div>
            @endif
        </div>
    </x-card>
</div>
