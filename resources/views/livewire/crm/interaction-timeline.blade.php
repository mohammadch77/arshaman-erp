<div>
    <x-card shadow class="mb-4">
        <x-slot:title>ثبت تعامل جدید</x-slot:title>
        <x-form wire:submit="record" class="gap-5">
            <x-select
                label="پروفایل سایت"
                wire:model="contact_site_profile_id"
                :options="$siteProfileOptions"
                option-value="id"
                option-label="label"
                placeholder="انتخاب شرکت"
                placeholder-value=""
                :icon="theme_icon('site')"
                required
            />

            <x-select
                label="نوع تعامل"
                wire:model="interaction_type"
                :options="[
                    ['id' => 'call', 'name' => 'تماس تلفنی'],
                    ['id' => 'telegram', 'name' => 'تلگرام'],
                    ['id' => 'site_form', 'name' => 'فرم سایت'],
                ]"
                option-value="id"
                option-label="name"
                :icon="theme_icon('timeline')"
                required
            />

            <x-textarea label="یادداشت" wire:model="notes" :icon="theme_icon('note')" rows="2" />

            <x-slot:actions>
                <x-button
                    label="ثبت تعامل"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="record"
                />
            </x-slot:actions>
        </x-form>
    </x-card>

    <x-card shadow>
        <x-slot:title>تایم‌لاین تعاملات</x-slot:title>

        @if ($interactions->isEmpty())
            <div class="text-sm opacity-60">هنوز تعاملی ثبت نشده است.</div>
        @else
            <ul class="timeline timeline-vertical">
                @foreach ($interactions as $interaction)
                    <li>
                        @unless ($loop->first)
                            <hr />
                        @endunless
                        <div class="timeline-start text-sm opacity-60">
                            {{ \App\Support\Jalali::toDisplayDateTime($interaction->occurred_at) }}
                        </div>
                        <div class="timeline-middle">
                            <x-icon :name="theme_icon(str_replace('_', '-', $interaction->interaction_type))" class="w-4 h-4" />
                        </div>
                        <div class="timeline-end timeline-box">
                            <div class="font-semibold">{{ $interaction->company->name }}</div>
                            @if ($interaction->notes)
                                <div class="text-sm opacity-80">{{ $interaction->notes }}</div>
                            @endif
                            @if ($interaction->createdBy)
                                <div class="text-xs opacity-50">ثبت‌شده توسط {{ $interaction->createdBy->name }}</div>
                            @endif
                        </div>
                        @unless ($loop->last)
                            <hr />
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
