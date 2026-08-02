<div>
    <x-header title="سال‌های مالی" subtitle="محدوده هر سال مالی شمسی و وضعیت باز/بسته‌بودن آن" separator />

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'name', 'label' => 'سال مالی'],
                ['key' => 'range', 'label' => 'محدوده'],
                ['key' => 'status', 'label' => 'وضعیت'],
                ['key' => 'closed_by', 'label' => 'بسته‌شده توسط'],
                ['key' => 'actions', 'label' => ''],
            ]"
            :rows="$periods"
        >
            @scope('cell_range', $period)
                {{ \App\Support\Jalali::toDisplay($period->start_date) }}
                تا
                {{ \App\Support\Jalali::toDisplay($period->end_date) }}
            @endscope

            @scope('cell_status', $period)
                @if($period->is_closed)
                    <x-badge value="بسته" class="badge-neutral" />
                @else
                    <x-badge value="باز" class="badge-success" />
                @endif
            @endscope

            @scope('cell_closed_by', $period)
                @if($period->is_closed)
                    {{ $period->closedBy?->full_name ?? '—' }}
                    <div class="text-xs opacity-60">{{ \App\Support\Jalali::toDisplayDateTime($period->closed_at) }}</div>
                @else
                    —
                @endif
            @endscope

            @scope('cell_actions', $period)
                @can('close', $period)
                    @if(! $period->is_closed)
                        <x-button
                            label="بستن"
                            :icon="theme_icon('finalize')"
                            class="btn-sm btn-outline"
                            wire:click="close('{{ $period->id }}')"
                            wire:confirm="بستن سال مالی غیرقابل بازگشت است. ادامه می‌دهید؟"
                            spinner="close('{{ $period->id }}')"
                        />
                    @endif
                @endcan
            @endscope
        </x-table>
    </x-card>
</div>
