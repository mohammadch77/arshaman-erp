<div>
    <x-header title="پیام‌های تماس با ما" subtitle="پیام‌های ثبت‌شده توسط بازدیدکنندگان سایت" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="filterStatus"
                :options="$this->statusOptions"
                option-value="id"
                option-label="name"
            />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'full_name', 'label' => 'نام'],
                ['key' => 'phone', 'label' => 'موبایل'],
                ['key' => 'subject', 'label' => 'موضوع'],
                ['key' => 'message', 'label' => 'پیام'],
                ['key' => 'status', 'label' => 'وضعیت'],
                ['key' => 'created_at', 'label' => 'تاریخ ثبت'],
                ['key' => 'actions', 'label' => ''],
            ]"
            :rows="$submissions"
            with-pagination
        >
            @scope('cell_phone', $submission)
                {{ \App\Support\Farsi::toDigits($submission->phone) }}
            @endscope

            @scope('cell_subject', $submission)
                {{ $submission->subject ?? '—' }}
            @endscope

            @scope('cell_message', $submission)
                <span class="line-clamp-2 max-w-xs">{{ $submission->message }}</span>
            @endscope

            @scope('cell_status', $submission)
                @php
                    $badgeClass = match ($submission->status) {
                        \App\Modules\CRM\Enums\ContactSubmissionStatus::New => 'badge-info',
                        \App\Modules\CRM\Enums\ContactSubmissionStatus::Read => 'badge-warning',
                        \App\Modules\CRM\Enums\ContactSubmissionStatus::InProgress => 'badge-secondary',
                        \App\Modules\CRM\Enums\ContactSubmissionStatus::Replied => 'badge-success',
                        \App\Modules\CRM\Enums\ContactSubmissionStatus::Archived => 'badge-ghost',
                    };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $submission->status->label() }}</span>
            @endscope

            @scope('cell_created_at', $submission)
                {{ \App\Support\Jalali::toDisplayDateTime($submission->created_at) }}
            @endscope

            @scope('actions', $submission)
                <div class="flex gap-1">
                    @if ($submission->status === \App\Modules\CRM\Enums\ContactSubmissionStatus::New)
                        <x-button
                            :icon="theme_icon('profile')"
                            tooltip-left="علامت‌گذاری به‌عنوان خوانده‌شده"
                            class="btn-circle btn-ghost btn-sm"
                            wire:click="markStatus('{{ $submission->id }}', 'read')"
                        />
                    @endif

                    @if ($submission->status !== \App\Modules\CRM\Enums\ContactSubmissionStatus::Replied && $submission->status !== \App\Modules\CRM\Enums\ContactSubmissionStatus::Archived)
                        <x-button
                            :icon="theme_icon('call')"
                            tooltip-left="ثبت نتیجه تماس"
                            class="btn-circle btn-ghost btn-sm"
                            wire:click="openAttemptModal('{{ $submission->id }}')"
                        />
                    @endif

                    @if ($submission->status !== \App\Modules\CRM\Enums\ContactSubmissionStatus::Archived)
                        <x-button
                            :icon="theme_icon('archive')"
                            tooltip-left="بایگانی"
                            class="btn-circle btn-ghost btn-sm"
                            wire:click="markStatus('{{ $submission->id }}', 'archived')"
                        />
                    @endif

                    <x-button
                        :icon="theme_icon('history')"
                        tooltip-left="تاریخچه"
                        class="btn-circle btn-ghost btn-sm"
                        wire:click="openHistory('{{ $submission->id }}')"
                    />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-modal wire:model="showAttemptModal" title="ثبت نتیجه تماس" separator>
        <x-form wire:submit="logAttempt" class="gap-5">
            <x-select
                label="نتیجه تماس"
                wire:model="attemptOutcome"
                :options="$this->outcomeOptions"
                option-value="id"
                option-label="name"
                placeholder="انتخاب کنید..."
                placeholder-value=""
                :icon="theme_icon('call')"
                required
            />

            <x-textarea
                label="یادداشت (اختیاری)"
                wire:model="attemptNote"
                :icon="theme_icon('note')"
                rows="3"
            />

            <x-slot:actions>
                <x-button label="انصراف" @click="$wire.showAttemptModal = false" />
                <x-button label="ثبت" type="submit" class="btn-primary" spinner="logAttempt" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="showHistoryModal" title="تاریخچه" separator>
        @if ($history->isEmpty())
            <p class="text-base-content/60">هنوز هیچ رویدادی برای این پیام ثبت نشده است.</p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($history as $event)
                    <li class="border-b border-base-300 pb-3 last:border-b-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            <x-icon :name="theme_icon($event['type'] === 'attempt' ? 'call' : 'history')" class="w-4 h-4 text-base-content/60" />
                            <span class="font-medium">{{ $event['user']?->full_name ?? 'نامشخص' }}</span>
                        </div>

                        @if ($event['type'] === 'status_change')
                            <div class="text-sm mt-1">
                                از
                                <span class="badge badge-sm">{{ \App\Modules\CRM\Enums\ContactSubmissionStatus::tryFrom($event['from'] ?? '')?->label() ?? '—' }}</span>
                                به
                                <span class="badge badge-sm badge-primary">{{ \App\Modules\CRM\Enums\ContactSubmissionStatus::tryFrom($event['to'] ?? '')?->label() ?? '—' }}</span>
                            </div>
                        @else
                            <div class="text-sm mt-1">
                                نتیجه تماس:
                                <span class="badge badge-sm badge-accent">{{ $event['outcome']->label() }}</span>
                            </div>
                            @if ($event['note'])
                                <p class="text-sm text-base-content/70 mt-1 whitespace-pre-line">{{ $event['note'] }}</p>
                            @endif
                        @endif

                        <div class="text-xs text-base-content/60 mt-1">
                            {{ \App\Support\Jalali::toDisplayDateTime($event['at']) }}
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
