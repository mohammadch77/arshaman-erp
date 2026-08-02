<div>
    <x-header
        title="ثبت نرخ ارز"
        subtitle="نرخ روزانه یک ارز به تومان"
        separator
    />

    <x-card shadow class="max-w-2xl">
        <x-form wire:submit="save" class="gap-5">
            <x-select
                label="ارز"
                wire:model="currency_id"
                :options="$this->currencyOptions"
                option-value="id"
                option-label="name"
                placeholder="انتخاب ارز"
                placeholder-value=""
                :icon="theme_icon('currency')"
                required
            />

            <x-input
                label="نرخ (هر ۱ واحد ارز چند تومان)"
                wire:model="rate_to_toman"
                type="number"
                step="0.01"
                :icon="theme_icon('money')"
                required
            />

            <x-jalali-date-select
                field="effective_date"
                label="تاریخ اعتبار"
                :year="$jalaliParts['effective_date']['year']"
                :month="$jalaliParts['effective_date']['month']"
                required
            />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('exchange-rates.index') }}" class="btn-ghost" />
                <x-button
                    label="ثبت نرخ"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
