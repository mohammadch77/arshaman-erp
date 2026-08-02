<div>
    <x-header title="مخاطب جدید" subtitle="ثبت مخاطب برای شرکت جاری" separator />

    <x-card shadow class="max-w-2xl">
        @if ($duplicateContactId)
            <x-alert :icon="theme_icon('warning')" class="alert-warning mb-4">
                <div class="flex flex-col gap-2">
                    <div class="text-sm leading-relaxed">
                        این مخاطب (بر اساس موبایل یا ایمیل) از قبل وجود دارد و در این شرکت هم پروفایل دارد.
                    </div>
                    <div>
                        <x-button
                            label="مشاهده پروفایل موجود"
                            :icon="theme_icon('profile')"
                            class="btn-sm btn-outline"
                            link="{{ route('contacts.profile', $duplicateContactId) }}"
                        />
                    </div>
                </div>
            </x-alert>
        @endif

        <x-form wire:submit="save" class="gap-5">
            <x-input label="نام کامل" wire:model="full_name" :icon="theme_icon('contact')" required />
            <x-input label="موبایل" wire:model="phone" :icon="theme_icon('phone')" required />
            <x-input label="ایمیل" wire:model="email" type="email" :icon="theme_icon('email')" />
            <x-input label="نام محلی (اختیاری)" wire:model="site_full_name" :icon="theme_icon('site')" hint="اگر نام این مخاطب در این سایت با نام هلدینگی فرق دارد" />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('contacts.index') }}" class="btn-ghost" />
                <x-button label="ثبت مخاطب" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
