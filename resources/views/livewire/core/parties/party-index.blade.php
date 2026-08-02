<div>
    <x-header title="طرف‌حساب‌ها" subtitle="فهرست مشتریان و تأمین‌کنندگان" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام یا تلفن..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-select
                wire:model.live="typeFilter"
                :options="$this->typeFilterOptions"
                option-value="id"
                option-label="name"
                placeholder="همه انواع"
                placeholder-value=""
            />
            <x-button label="طرف‌حساب جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('parties.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'نام'],
                ['key' => 'party_type', 'label' => 'نوع شخص'],
                ['key' => 'roles', 'label' => 'نقش'],
                ['key' => 'phone', 'label' => 'تلفن'],
            ]"
            :rows="$parties"
            with-pagination
        >
            @scope('cell_party_type', $party)
                {{ $party->party_type->label() }}
            @endscope

            @scope('cell_roles', $party)
                <div class="flex items-center gap-1">
                    @if($party->is_customer)
                        <x-badge value="مشتری" class="badge-success" />
                    @endif
                    @if($party->is_supplier)
                        <x-badge value="تأمین‌کننده" class="badge-info" />
                    @endif
                </div>
            @endscope

            @scope('cell_phone', $party)
                {{ $party->phone ? \App\Support\Farsi::toDigits($party->phone) : '—' }}
            @endscope

            @scope('actions', $party)
                <x-button
                    :icon="theme_icon('edit')"
                    tooltip-left="ویرایش"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('parties.edit', $party->id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
