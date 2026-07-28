<div>
    <x-header title="ساخت کاربر جدید" subtitle="کاربر داخلی با رمز اولیه" separator />

    <x-card shadow class="max-w-xl">
        <x-form wire:submit="save" class="gap-5">
            <x-input
                label="نام کامل"
                wire:model="full_name"
                :icon="theme_icon('user')"
                required
            />

            <x-input
                label="ایمیل"
                wire:model="email"
                :icon="theme_icon('email')"
                type="email"
                required
            />

            <x-password
                label="رمز اولیه"
                wire:model="password"
                :icon="theme_icon('password')"
                hint="حداقل ۸ کاراکتر"
                right
                required
            />

            <x-password
                label="تکرار رمز"
                wire:model="password_confirmation"
                :icon="theme_icon('password')"
                right
                required
            />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('users.index') }}" class="btn-ghost" />
                <x-button label="ساخت کاربر" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
