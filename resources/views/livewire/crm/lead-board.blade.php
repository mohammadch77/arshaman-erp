<div>
    <x-header title="قیف فروش" subtitle="سرنخ‌های شرکت جاری بر اساس مرحله" separator>
        <x-slot:actions>
            <x-button label="لید جدید" :icon="theme_icon('add')" class="btn-primary" wire:click="$set('showCreateForm', true)" responsive />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
        @foreach ($stages as $stage)
            <x-card shadow class="!p-0">
                <x-slot:title class="text-sm">
                    {{ \App\Modules\CRM\Models\Lead::stageLabel($stage) }}
                    <x-badge :value="\App\Support\Farsi::toDigits(($leadsByStage[$stage] ?? collect())->count())" class="badge-neutral badge-sm" />
                </x-slot:title>

                <div class="flex flex-col gap-3 px-4 pb-4">
                    @forelse (($leadsByStage[$stage] ?? collect()) as $lead)
                        <div class="border border-base-300 rounded-box p-3">
                            <div class="font-semibold text-sm">
                                {{ $lead->contactSiteProfile?->contact?->full_name ?? 'بدون مخاطب' }}
                            </div>
                            <div class="text-xs opacity-60">{{ \App\Modules\CRM\Models\Lead::sourceLabel($lead->source) }}</div>

                            @if ($lead->estimated_value)
                                <div class="text-xs mt-1">@toman($lead->estimated_value)</div>
                            @endif

                            @if ($lead->notes)
                                <div class="text-xs opacity-80 mt-1">{{ $lead->notes }}</div>
                            @endif

                            <x-select
                                class="mt-2 select-sm"
                                :options="$assignableUsers"
                                option-value="id"
                                option-label="full_name"
                                placeholder="تخصیص به..."
                                placeholder-value=""
                                :value="$lead->assigned_to_user_id"
                                wire:change="assign('{{ $lead->id }}', $event.target.value)"
                                :icon="theme_icon('assign')"
                            />

                            @if (! empty(\App\Modules\CRM\Models\Lead::TRANSITIONS[$stage]))
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach (\App\Modules\CRM\Models\Lead::TRANSITIONS[$stage] as $nextStage)
                                        <x-button
                                            :label="\App\Modules\CRM\Models\Lead::stageLabel($nextStage)"
                                            :icon="$nextStage === 'lost' ? theme_icon('reject') : theme_icon('approve')"
                                            class="btn-xs {{ $nextStage === 'lost' ? 'btn-error btn-outline' : 'btn-success btn-outline' }}"
                                            wire:click="changeStage('{{ $lead->id }}', '{{ $nextStage }}')"
                                        />
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-xs opacity-50">خالی</div>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>

    <x-modal wire:model="showCreateForm" title="لید جدید" separator>
        <x-form wire:submit="create" class="gap-5">
            <x-select
                label="مخاطب (اختیاری)"
                wire:model="contact_site_profile_id"
                :options="$siteProfileOptions"
                option-value="id"
                option-label="label"
                placeholder="بدون مخاطب"
                placeholder-value=""
                :icon="theme_icon('contact')"
            />

            <x-select
                label="منبع"
                wire:model="source"
                :options="collect(\App\Modules\CRM\Models\Lead::SOURCES)->map(fn ($s) => ['id' => $s, 'name' => \App\Modules\CRM\Models\Lead::sourceLabel($s)])"
                option-value="id"
                option-label="name"
                :icon="theme_icon('lead')"
                required
            />

            <x-input label="ارزش تخمینی (تومان)" wire:model="estimated_value" :icon="theme_icon('money')" type="number" />

            <x-textarea label="یادداشت" wire:model="notes" :icon="theme_icon('note')" rows="2" />

            <x-slot:actions>
                <x-button label="ثبت" type="submit" class="btn-primary" :icon="theme_icon('save')" spinner="create" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
