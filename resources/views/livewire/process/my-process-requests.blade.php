<div>
    <x-header title="درخواست‌های من" subtitle="تاریخچه‌ی فرایندهایی که خودتان شروع کرده‌اید" separator>
        <x-slot:actions>
            <x-button label="درخواست جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('processes.request') }}" />
        </x-slot:actions>
    </x-header>

    @if($this->requests->isEmpty())
        <x-card shadow>
            <div class="flex flex-col items-center gap-2 py-10 text-base-content/60">
                <x-icon :name="theme_icon('history')" class="w-10 h-10" />
                <p>هنوز هیچ درخواستی ثبت نکرده‌اید.</p>
            </div>
        </x-card>
    @else
        <div class="flex flex-col gap-4">
            @foreach($this->requests as $row)
                @php($instance = $row['instance'])
                @php($statusBadge = match ($instance->status) {
                    \App\Modules\Process\Enums\ProcessStatus::InProgress => 'badge-info',
                    \App\Modules\Process\Enums\ProcessStatus::Approved => 'badge-success',
                    \App\Modules\Process\Enums\ProcessStatus::Rejected => 'badge-error',
                    \App\Modules\Process\Enums\ProcessStatus::Cancelled => 'badge-ghost',
                })
                <x-card shadow>
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <x-icon :name="theme_icon('process')" class="w-5 h-5 text-primary" />
                                <span class="font-medium">{{ $instance->definition->name }}</span>
                                <span class="badge {{ $statusBadge }} badge-sm">{{ $instance->status->label() }}</span>
                            </div>

                            <p class="text-xs text-base-content/60 mt-1">
                                شروع در {{ \App\Support\Jalali::toDisplayDateTime($instance->started_at) }}
                                @if($instance->status === \App\Modules\Process\Enums\ProcessStatus::InProgress)
                                    — مرحله‌ی فعلی: {{ $instance->currentStep?->name }}
                                @endif
                            </p>

                            @if($row['summary'] !== [])
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 mt-3 text-sm">
                                    @foreach($row['summary'] as $item)
                                        <div class="flex gap-2">
                                            <dt class="text-base-content/60">{{ $item['label'] }}:</dt>
                                            <dd class="font-medium">{{ $item['value'] }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>

                        <x-button
                            :icon="theme_icon('history')"
                            tooltip-left="تاریخچه"
                            class="btn-circle btn-ghost btn-sm shrink-0"
                            wire:click="openHistory('{{ $instance->id }}')"
                        />
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="showHistoryModal" title="تاریخچه‌ی فرایند" separator>
        @if($this->history->isEmpty())
            <p class="text-base-content/60">هنوز هیچ رویدادی ثبت نشده است.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach($this->history as $event)
                    <li class="border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            <x-icon :name="theme_icon('history')" class="w-4 h-4 text-base-content/60" />
                            <span class="font-medium">{{ $event->step->name }}</span>
                            <span class="badge badge-ghost badge-sm">{{ $event->action->label() }}</span>
                        </div>

                        <div class="text-sm mt-1 text-base-content/70">
                            {{ $event->actor?->full_name ?? 'خودکار (سیستم)' }}
                        </div>

                        @if($event->comment)
                            <p class="text-sm text-base-content/70 mt-1 whitespace-pre-line">{{ $event->comment }}</p>
                        @endif

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ \App\Support\Jalali::toDisplayDateTime($event->created_at) }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot:actions>
            <x-button label="بستن" @click="$wire.showHistoryModal = false" />
        </x-slot:actions>
    </x-modal>
</div>
