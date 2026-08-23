<div>
    <x-card shadow>
        <x-slot:title>تیکت‌های پشتیبانی</x-slot:title>

        @if ($tickets->isEmpty())
            <div class="text-sm opacity-60">هنوز تیکتی برای این مخاطب ثبت نشده است.</div>
        @else
            <x-table
                :headers="[
                    ['key' => 'subject', 'label' => 'موضوع'],
                    ['key' => 'company', 'label' => 'شرکت'],
                    ['key' => 'status', 'label' => 'وضعیت'],
                    ['key' => 'created_at', 'label' => 'تاریخ ثبت'],
                    ['key' => 'actions', 'label' => ''],
                ]"
                :rows="$tickets"
            >
                @scope('cell_company', $ticket)
                    {{ $ticket->company->name }}
                @endscope

                @scope('cell_status', $ticket)
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
                @endscope

                @scope('cell_created_at', $ticket)
                    {{ \App\Support\Jalali::toDisplayDateTime($ticket->created_at) }}
                @endscope

                @scope('cell_actions', $ticket)
                    <x-button
                        :icon="theme_icon('preview')"
                        tooltip-left="مشاهده تیکت"
                        class="btn-circle btn-ghost btn-sm"
                        link="{{ route('tickets.show', $ticket->id) }}"
                    />
                @endscope
            </x-table>
        @endif
    </x-card>
</div>
