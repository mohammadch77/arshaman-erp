<div>
    <x-header title="{{ $contact->full_name }}" subtitle="نمای ۳۶۰ مخاطب — پروفایل هلدینگی و سایت‌ها" separator>
        <x-slot:actions>
            <x-button label="بازگشت" link="{{ route('contacts.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <x-card shadow class="mb-4">
        <x-slot:title>پروفایل هلدینگی</x-slot:title>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <div class="text-sm opacity-60">موبایل</div>
                <div>{{ \App\Support\Farsi::toDigits($contact->phone) }}</div>
            </div>
            <div>
                <div class="text-sm opacity-60">ایمیل</div>
                <div>{{ $contact->email ?? '—' }}</div>
            </div>
            <div>
                <div class="text-sm opacity-60">تعداد سایت‌های فعال</div>
                <div>{{ \App\Support\Farsi::toDigits((string) $siteProfiles->count()) }}</div>
            </div>
        </div>
    </x-card>

    <x-card shadow>
        <x-slot:title>پروفایل‌های سایت</x-slot:title>
        <x-table
            :headers="[
                ['key' => 'company', 'label' => 'شرکت'],
                ['key' => 'site_full_name', 'label' => 'نام محلی'],
                ['key' => 'first_seen_at', 'label' => 'اولین مشاهده'],
                ['key' => 'total_purchase_amount', 'label' => 'جمع خرید'],
            ]"
            :rows="$siteProfiles"
        >
            @scope('cell_company', $profile)
                {{ $profile->company->name }}
            @endscope

            @scope('cell_site_full_name', $profile)
                {{ $profile->site_full_name ?? '—' }}
            @endscope

            @scope('cell_first_seen_at', $profile)
                {{ $profile->first_seen_at ? \App\Support\Jalali::toDisplayDateTime($profile->first_seen_at) : '—' }}
            @endscope

            @scope('cell_total_purchase_amount', $profile)
                @toman($profile->total_purchase_amount)
            @endscope
        </x-table>
    </x-card>

    <div class="mt-4">
        @livewire('crm.interaction-timeline', ['contactId' => $contact->id], key('interaction-timeline-'.$contact->id))
    </div>

    <div class="mt-4">
        @livewire('crm.ticket-timeline', ['contactId' => $contact->id], key('ticket-timeline-'.$contact->id))
    </div>
</div>
