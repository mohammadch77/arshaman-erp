<div>
    @if($invitationIsValid)
        <x-card
            title="قبول دعوت"
            subtitle="برای فعال‌سازی حساب، نام و رمز عبور خود را تعیین کنید"
            class="border border-base-300 shadow-xl rounded-2xl"
            separator
        >
            <x-form wire:submit="accept" class="gap-5">
                <x-input
                    label="نام کامل"
                    wire:model="full_name"
                    :icon="theme_icon('user')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    required
                />

                <x-password
                    label="رمز عبور"
                    wire:model="password"
                    :icon="theme_icon('password')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    hint="حداقل ۸ کاراکتر"
                    right
                    required
                />

                <x-password
                    label="تکرار رمز عبور"
                    wire:model="password_confirmation"
                    :icon="theme_icon('password')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    right
                    required
                />

                <x-slot:actions>
                    <x-button
                        label="فعال‌سازی حساب"
                        type="submit"
                        class="btn-primary w-full shadow-sm"
                        :icon="theme_icon('login')"
                        spinner="accept"
                    />
                </x-slot:actions>
            </x-form>
        </x-card>
    @else
        <x-card
            title="دعوت‌نامه نامعتبر"
            class="border border-base-300 shadow-xl rounded-2xl"
            separator
        >
            <x-alert title="{{ $invalidMessage }}" icon="{{ theme_icon('deactivate') }}" class="alert-error" />

            <x-slot:actions>
                <x-button label="بازگشت به ورود" link="{{ route('login') }}" class="btn-primary w-full" />
            </x-slot:actions>
        </x-card>
    @endif
</div>
