<div>
    <x-header title="مخاطبین" subtitle="فهرست مخاطبین شرکت جاری" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام، موبایل یا ایمیل..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="مخاطب جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('contacts.create') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'full_name', 'label' => 'نام'],
                ['key' => 'phone', 'label' => 'موبایل'],
                ['key' => 'email', 'label' => 'ایمیل'],
                ['key' => 'site_full_name', 'label' => 'نام محلی'],
                ['key' => 'total_purchase_amount', 'label' => 'جمع خرید'],
            ]"
            :rows="$profiles"
            with-pagination
        >
            @scope('cell_full_name', $profile)
                {{ $profile->contact->full_name }}
            @endscope

            @scope('cell_phone', $profile)
                {{ \App\Support\Farsi::toDigits($profile->contact->phone) }}
            @endscope

            @scope('cell_email', $profile)
                {{ $profile->contact->email ?? '—' }}
            @endscope

            @scope('cell_site_full_name', $profile)
                {{ $profile->site_full_name ?? '—' }}
            @endscope

            @scope('cell_total_purchase_amount', $profile)
                @toman($profile->total_purchase_amount)
            @endscope

            @scope('actions', $profile)
                <x-button
                    :icon="theme_icon('profile')"
                    tooltip-left="نمای ۳۶۰"
                    class="btn-circle btn-ghost btn-sm"
                    link="{{ route('contacts.profile', $profile->contact_id) }}"
                />
            @endscope
        </x-table>
    </x-card>
</div>
