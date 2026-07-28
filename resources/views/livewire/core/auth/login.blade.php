<div>
    <x-card
        title="ورود به سامانه"
        subtitle="برای ادامه، اطلاعات حساب کاربری خود را وارد کنید"
        class="border border-base-300 shadow-xl rounded-2xl"
        separator
    >
        <x-form wire:submit="authenticate" class="gap-5">
            <x-input
                label="ایمیل"
                wire:model="email"
                :icon="theme_icon('email')"
                type="email"
                class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                autofocus
                required
            />

            <x-password
                label="رمز عبور"
                wire:model="password"
                :icon="theme_icon('password')"
                class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                right
                required
            />

            <x-checkbox
                label="مرا به خاطر بسپار"
                wire:model="remember"
                class="checkbox-primary checkbox-lg border-2 border-base-300"
            />

            <x-slot:actions>
                <x-button
                    label="ورود"
                    type="submit"
                    class="btn-primary w-full shadow-sm"
                    :icon="theme_icon('login')"
                    spinner="authenticate"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
