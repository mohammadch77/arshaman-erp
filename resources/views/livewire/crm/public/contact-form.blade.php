<div>
    @if ($submitted)
        <x-card
            title="پیام شما ثبت شد"
            subtitle="{{ $company->name }} در اسرع وقت با شما تماس می‌گیرد"
            class="border border-base-300 shadow-xl rounded-2xl"
            separator
        >
            <div class="flex flex-col items-center gap-3 py-4 text-center">
                <x-icon name="o-check-circle" class="w-12 h-12 text-success" />
                <p>از تماس شما با {{ $company->name }} سپاسگزاریم.</p>
            </div>

            <x-slot:actions>
                <x-button label="ثبت پیام دیگر" wire:click="$set('submitted', false)" class="btn-primary w-full" />
            </x-slot:actions>
        </x-card>
    @else
        <x-card
            title="تماس با {{ $company->name }}"
            subtitle="پیام خود را برای ما بنویسید"
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

                <x-input
                    label="موضوع (اختیاری)"
                    wire:model="subject"
                    :icon="theme_icon('note')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                />

                <x-textarea
                    label="پیام شما"
                    wire:model="message"
                    :icon="theme_icon('message')"
                    class="border border-base-300 bg-base-100 focus-within:border-primary ps-2 pe-3"
                    rows="4"
                    required
                />

                {{-- تله ضدربات: از دید کاربر واقعی کاملاً مخفی است، فقط بات‌ها این را پر می‌کنند --}}
                <div class="absolute -z-10 opacity-0 h-0 w-0 overflow-hidden" aria-hidden="true">
                    <label for="website">وب‌سایت</label>
                    <input type="text" id="website" wire:model="website" tabindex="-1" autocomplete="off">
                </div>

                <x-slot:actions>
                    <x-button
                        label="ارسال پیام"
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
