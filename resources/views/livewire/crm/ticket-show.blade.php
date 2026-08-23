<div>
    <x-header title="{{ $ticket->subject }}" subtitle="تیکت پشتیبانی — {{ $ticket->contactSiteProfile->contact->full_name }}" separator>
        <x-slot:actions>
            <x-button label="بازگشت" link="{{ route('tickets.index') }}" class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    @if ($ticket->isClosed())
        <x-alert
            title="این تیکت بسته شده است"
            description="ثبت پاسخ همچنان امکان‌پذیر است، اما پیش از پاسخ به این نکته توجه کنید."
            :icon="theme_icon('warning')"
            class="alert-warning mb-4"
        />
    @endif

    <x-card shadow class="mb-4">
        <x-slot:title>اطلاعات تیکت</x-slot:title>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <div class="text-sm opacity-60">مخاطب</div>
                <div>{{ $ticket->contactSiteProfile->contact->full_name }}</div>
            </div>
            <div>
                <div class="text-sm opacity-60">اولویت</div>
                <x-badge
                    value="{{ $ticket->priorityEnum()->label() }}"
                    class="{{ match ($ticket->priority) {
                        'high' => 'badge-error',
                        'low' => 'badge-ghost',
                        default => 'badge-neutral',
                    } }}"
                />
            </div>
            <div>
                <div class="text-sm opacity-60">وضعیت فعلی</div>
                <x-badge
                    value="{{ $ticket->statusEnum()->label() }}"
                    class="{{ match ($ticket->status) {
                        'open' => 'badge-info',
                        'in_progress' => 'badge-warning',
                        'resolved' => 'badge-success',
                        'closed' => 'badge-ghost',
                        default => 'badge-neutral',
                    } }}"
                />
            </div>
            <div>
                <div class="text-sm opacity-60">تاریخ ثبت</div>
                <div>{{ \App\Support\Jalali::toDisplayDateTime($ticket->created_at) }}</div>
            </div>
        </div>

        @if ($ticket->description)
            <div class="mt-4">
                <div class="text-sm opacity-60 mb-1">توضیحات</div>
                <div class="whitespace-pre-line">{{ $ticket->description }}</div>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <x-select
                label="تغییر وضعیت"
                wire:model="newStatus"
                :options="$statusOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('process')"
                class="max-w-xs"
            />
            <x-button
                label="اعمال وضعیت"
                :icon="theme_icon('save')"
                class="btn-primary"
                wire:click="changeStatus"
                spinner="changeStatus"
            />
        </div>
    </x-card>

    <x-card shadow class="mb-4">
        <x-slot:title>ثبت پاسخ</x-slot:title>
        <x-form wire:submit="reply" class="gap-5">
            <x-textarea label="پیام" wire:model="message" :icon="theme_icon('note')" rows="3" required />

            <x-slot:actions>
                <x-button
                    label="ارسال پاسخ"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('send')"
                    spinner="reply"
                />
            </x-slot:actions>
        </x-form>
    </x-card>

    <x-card shadow>
        <x-slot:title>تایم‌لاین پاسخ‌ها</x-slot:title>

        @if ($ticket->replies->isEmpty())
            <div class="text-sm opacity-60">هنوز پاسخی ثبت نشده است.</div>
        @else
            <ul class="timeline timeline-vertical">
                @foreach ($ticket->replies as $reply)
                    <li>
                        @unless ($loop->first)
                            <hr />
                        @endunless
                        <div class="timeline-start text-sm opacity-60">
                            {{ \App\Support\Jalali::toDisplayDateTime($reply->created_at) }}
                        </div>
                        <div class="timeline-middle">
                            <x-icon :name="theme_icon('note')" class="w-4 h-4" />
                        </div>
                        <div class="timeline-end timeline-box">
                            <div class="font-semibold">{{ $reply->user->full_name }}</div>
                            <div class="text-sm opacity-80 whitespace-pre-line">{{ $reply->message }}</div>
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
