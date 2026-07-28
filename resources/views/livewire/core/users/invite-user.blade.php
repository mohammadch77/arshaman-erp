<div>
    <x-header title="دعوت کاربر جدید" subtitle="لینک دعوت برای تعیین نام و رمز عبور ارسال می‌شود" separator />

    <x-card shadow class="max-w-xl">
        <x-form wire:submit="invite" class="gap-5">
            <x-input label="نام کامل" wire:model="full_name" :icon="theme_icon('user')" required />
            <x-input label="ایمیل" wire:model="email" :icon="theme_icon('email')" type="email" required />

            <x-select
                label="شرکت (اختیاری)"
                wire:model="companyId"
                :options="$this->companies"
                option-value="id"
                option-label="name"
                placeholder="بدون شرکت"
                placeholder-value=""
            />

            <x-select
                label="نقش (اختیاری)"
                wire:model="roleId"
                :options="$this->roles"
                option-value="id"
                option-label="display_name"
                placeholder="بدون نقش"
                placeholder-value=""
            />

            <x-slot:actions>
                <x-button label="انصراف" link="{{ route('users.index') }}" class="btn-ghost" />
                <x-button label="ارسال دعوت" type="submit" class="btn-primary" :icon="theme_icon('invite')" spinner="invite" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>
