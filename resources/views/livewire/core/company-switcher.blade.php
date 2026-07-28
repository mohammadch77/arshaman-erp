<div>
    <x-dropdown right no-x-anchor>
        <x-slot:trigger>
            <x-button class="btn-ghost btn-sm normal-case" :icon="theme_icon('company')">
                @if($isAggregateView)
                    نمای تجمیعی هلدینگ
                @else
                    {{ $this->companies->firstWhere('id', $activeCompanyId)?->name ?? 'انتخاب شرکت' }}
                @endif
            </x-button>
        </x-slot:trigger>

        @foreach($this->companies as $company)
            <li>
                <a
                    wire:click.prevent="switchTo('{{ $company->id }}')"
                    wire:loading.attr="disabled"
                    @class(['active' => ! $isAggregateView && $activeCompanyId === $company->id])
                >
                    {{ $company->name }}
                </a>
            </li>
        @endforeach

        @if($isSuperAdmin)
            <x-menu-separator />

            <li>
                <a
                    wire:click.prevent="switchToAggregate"
                    wire:loading.attr="disabled"
                    @class(['active' => $isAggregateView])
                >
                    نمای تجمیعی هلدینگ
                </a>
            </li>
        @endif
    </x-dropdown>
</div>
