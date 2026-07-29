<div>
    <x-header title="حضور و غیاب من" subtitle="ثبت ورود و خروج امروز" separator />

    @if(! $employeeId)
        <x-card shadow>
            <div class="flex items-center gap-2 text-base-content/70">
                <x-icon :name="theme_icon('warning')" class="w-5 h-5" />
                <span>شما پرونده پرسنلی ندارید. برای ثبت حضور و غیاب باید ابتدا به یک پرونده کارمندی وصل شوید.</span>
            </div>
        </x-card>
    @else
        <x-card shadow class="max-w-md">
            <div class="mb-4 text-lg font-medium">{{ $employeeFullName }}</div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="stat bg-base-200 rounded-box p-4">
                    <div class="stat-title">ورود</div>
                    <div class="stat-value text-lg">
                        {{ $checkInAt ? \App\Support\Farsi::toDigits($checkInAt) : '—' }}
                    </div>
                </div>
                <div class="stat bg-base-200 rounded-box p-4">
                    <div class="stat-title">خروج</div>
                    <div class="stat-value text-lg">
                        {{ $checkOutAt ? \App\Support\Farsi::toDigits($checkOutAt) : '—' }}
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <x-button
                    label="ثبت ورود"
                    :icon="theme_icon('check-in')"
                    class="btn-primary flex-1"
                    wire:click="checkIn"
                    spinner="checkIn"
                    :disabled="(bool) $checkInAt"
                />
                <x-button
                    label="ثبت خروج"
                    :icon="theme_icon('check-out')"
                    class="btn-secondary flex-1"
                    wire:click="checkOut"
                    spinner="checkOut"
                    :disabled="! $checkInAt || (bool) $checkOutAt"
                />
            </div>
        </x-card>
    @endif
</div>
