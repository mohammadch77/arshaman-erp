<div>
    <x-header title="نرخ ارز" subtitle="تاریخچه نرخ روزانه ارزها به تومان" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="currencyFilter"
                :options="$this->currencyFilterOptions"
                option-value="id"
                option-label="name"
                placeholder="همه ارزها"
                placeholder-value=""
            />
            @can('create', \App\Modules\Core\Models\ExchangeRate::class)
                <x-button label="ثبت نرخ جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('exchange-rates.create') }}" responsive />
            @endcan
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'currency', 'label' => 'ارز'],
                ['key' => 'rate_to_toman', 'label' => 'نرخ (تومان)'],
                ['key' => 'effective_date', 'label' => 'تاریخ اعتبار'],
                ['key' => 'created_by', 'label' => 'ثبت‌شده توسط'],
            ]"
            :rows="$rates"
            with-pagination
        >
            @scope('cell_currency', $rate)
                {{ $rate->currency->code }} — {{ $rate->currency->name }}
            @endscope

            @scope('cell_rate_to_toman', $rate)
                {{ \App\Support\Farsi::toToman($rate->rate_to_toman) }}
            @endscope

            @scope('cell_effective_date', $rate)
                {{ \App\Support\Jalali::toDisplay($rate->effective_date) }}
            @endscope

            @scope('cell_created_by', $rate)
                {{ $rate->createdBy?->full_name ?? '—' }}
            @endscope
        </x-table>
    </x-card>
</div>
