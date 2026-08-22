<div>
    <x-header title="کمپین‌ها" subtitle="مدیریت کمپین‌ها و تاریخچه ارسال شبیه‌سازی‌شده" separator>
        <x-slot:actions>
            <x-button
                label="{{ $showCreateForm ? 'انصراف' : 'کمپین جدید' }}"
                :icon="theme_icon($showCreateForm ? 'cancel' : 'add')"
                class="{{ $showCreateForm ? 'btn-ghost' : 'btn-primary' }}"
                wire:click="$toggle('showCreateForm')"
            />
        </x-slot:actions>
    </x-header>

    <x-alert :icon="theme_icon('simulation')" class="alert-warning mb-4">
        حالت شبیه‌سازی — پیامی واقعاً ارسال نمی‌شود.
    </x-alert>

    @if ($showCreateForm)
        <x-card shadow class="mb-6 max-w-2xl">
            <x-form wire:submit="create" class="gap-5">
                <x-input label="نام کمپین" wire:model="name" :icon="theme_icon('campaign')" required />

                <x-select
                    label="نوع رخداد"
                    wire:model="triggerType"
                    :options="$this->triggerTypeOptions"
                    option-value="id"
                    option-label="name"
                    required
                />

                <x-select
                    label="کانال ارسال"
                    wire:model="channel"
                    :options="$this->channelOptions"
                    option-value="id"
                    option-label="name"
                    required
                />

                <x-textarea label="متن پیام (می‌توانید از {نام} برای نام مشتری استفاده کنید)" wire:model="messageTemplate" required />

                <x-slot:actions>
                    <x-button label="ذخیره" :icon="theme_icon('save')" class="btn-primary" type="submit" spinner="create" />
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif

    <x-card shadow class="mb-6 !p-0">
        <x-table :headers="[
            ['key' => 'name', 'label' => 'نام'],
            ['key' => 'trigger_type', 'label' => 'نوع رخداد'],
            ['key' => 'channel', 'label' => 'کانال'],
            ['key' => 'is_active', 'label' => 'فعال'],
            ['key' => 'actions', 'label' => ''],
        ]" :rows="$campaigns">
            @scope('cell_trigger_type', $campaign)
                {{ $campaign->trigger_type->label() }}
            @endscope

            @scope('cell_channel', $campaign)
                {{ $campaign->channel->label() }}
            @endscope

            @scope('cell_is_active', $campaign)
                <x-badge :value="$campaign->is_active ? 'فعال' : 'غیرفعال'" class="{{ $campaign->is_active ? 'badge-success' : 'badge-neutral' }}" />
            @endscope

            @scope('cell_actions', $campaign)
                <div class="flex gap-2 justify-end">
                    @if ($this->canTrigger($campaign))
                        <x-button
                            label="اجرای شبیه‌سازی"
                            :icon="theme_icon('trigger')"
                            class="btn-xs btn-outline"
                            wire:click="trigger('{{ $campaign->id }}')"
                            wire:confirm="کمپین برای همه مخاطبین غیرفعال شبیه‌سازی ارسال شود؟"
                            spinner="trigger('{{ $campaign->id }}')"
                        />
                    @endif

                    <x-button
                        label="{{ $campaign->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی' }}"
                        :icon="theme_icon($campaign->is_active ? 'deactivate' : 'activate')"
                        class="btn-xs btn-ghost"
                        wire:click="toggleActive('{{ $campaign->id }}')"
                        spinner="toggleActive('{{ $campaign->id }}')"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-header title="تاریخچه ارسال" subtitle="۵۰ رکورد اخیر" separator />

    <x-card shadow class="!p-0">
        <x-table :headers="[
            ['key' => 'campaign', 'label' => 'کمپین'],
            ['key' => 'contact', 'label' => 'مخاطب'],
            ['key' => 'channel', 'label' => 'کانال'],
            ['key' => 'status', 'label' => 'وضعیت'],
            ['key' => 'sent_at', 'label' => 'زمان'],
        ]" :rows="$logs">
            @scope('cell_campaign', $log)
                {{ $log->campaign?->name ?? '—' }}
            @endscope

            @scope('cell_contact', $log)
                {{ $log->contactSiteProfile?->contact?->full_name ?? '—' }}
            @endscope

            @scope('cell_channel', $log)
                {{ $log->channel->label() }}
            @endscope

            @scope('cell_status', $log)
                <x-badge value="شبیه‌سازی‌شده" class="badge-neutral" />
            @endscope

            @scope('cell_sent_at', $log)
                {{ $log->sent_at ? \App\Support\Jalali::toDisplayDateTime($log->sent_at) : '—' }}
            @endscope
        </x-table>
    </x-card>
</div>
