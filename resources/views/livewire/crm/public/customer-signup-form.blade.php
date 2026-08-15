<div>
    @if ($submitted)
        <x-card
            title="ثبت‌نام شما انجام شد"
            subtitle="{{ $company->name }} در اسرع وقت با شما در تماس خواهد بود"
            class="border border-base-300 shadow-xl rounded-2xl"
            separator
        >
            <div class="flex flex-col items-center gap-3 py-4 text-center">
                <x-icon name="o-check-circle" class="w-12 h-12 text-success" />
                <p>از ثبت‌نام شما در {{ $company->name }} سپاسگزاریم.</p>
            </div>

            <x-slot:actions>
                <x-button label="ثبت‌نام دیگر" wire:click="$set('submitted', false)" class="btn-primary w-full" />
            </x-slot:actions>
        </x-card>
    @else
        <x-card
            title="ثبت‌نام در {{ $company->name }}"
            subtitle="برای عضویت اطلاعات خود را وارد کنید"
            class="border border-base-300 shadow-xl rounded-2xl"
            separator
        >
            <x-form wire:submit="submit" class="gap-5">
                <x-input
                    label="نام و نام‌خانوادگی"
                    wire:model="full_name"
                    :icon="theme_icon('user')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    required
                />

                <x-input
                    label="شماره موبایل"
                    wire:model="phone"
                    :icon="theme_icon('phone')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    placeholder="09123456789"
                    required
                />

                <x-input
                    label="ایمیل (اختیاری)"
                    wire:model="email"
                    :icon="theme_icon('email')"
                    type="email"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                />

                {{-- تله ضدربات: از دید کاربر واقعی کاملاً مخفی است --}}
                <div class="absolute -z-10 opacity-0 h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="signup-website">وب‌سایت</label>
                    <input type="text" id="signup-website" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                <x-slot:actions>
                    <x-button
                        label="ثبت‌نام"
                        type="submit"
                        class="btn-primary w-full shadow-sm"
                        :icon="theme_icon('send')"
                        spinner="submit"
                    />
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif
</div>
