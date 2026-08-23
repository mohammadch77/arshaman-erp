<div>
    <x-header title="تیکت‌های پشتیبانی" subtitle="تیکت‌های ثبت‌شده برای شرکت فعال" separator>
        <x-slot:actions>
            <x-select
                wire:model.live="filterStatus"
                :options="$statusOptions"
                option-value="id"
                option-label="name"
            />
            <x-select
                wire:model.live="filterPriority"
                :options="$priorityOptions"
                option-value="id"
                option-label="name"
            />
            <x-button
                label="تیکت جدید"
                :icon="theme_icon('add')"
                class="btn-primary"
                @click="$wire.showCreateForm = true"
            />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'subject', 'label' => 'موضوع'],
                ['key' => 'contact', 'label' => 'مخاطب'],
                ['key' => 'priority', 'label' => 'اولویت'],
                ['key' => 'status', 'label' => 'وضعیت'],
                ['key' => 'assigned_to', 'label' => 'مسئول'],
                ['key' => 'created_at', 'label' => 'تاریخ ثبت'],
                ['key' => 'actions', 'label' => ''],
            ]"
            :rows="$tickets"
            with-pagination
        >
            @scope('cell_contact', $ticket)
                {{ $ticket->contactSiteProfile->contact->full_name }}
            @endscope

            @scope('cell_priority', $ticket)
                <x-badge
                    value="{{ $ticket->priorityEnum()->label() }}"
                    class="{{ match ($ticket->priority) {
                        'high' => 'badge-error',
                        'low' => 'badge-ghost',
                        default => 'badge-neutral',
                    } }}"
                />
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

            @scope('cell_assigned_to', $ticket)
                {{ $ticket->assignedTo->full_name ?? '—' }}
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
    </x-card>

    <x-modal wire:model="showCreateForm" title="ثبت تیکت جدید" separator>
        <x-form wire:submit="create" class="gap-5">
            <x-select
                label="پروفایل سایت مخاطب"
                wire:model="contact_site_profile_id"
                :options="$siteProfileOptions"
                option-value="id"
                option-label="label"
                placeholder="انتخاب مخاطب"
                placeholder-value=""
                :icon="theme_icon('contact')"
                required
            />

            <x-input label="موضوع" wire:model="subject" :icon="theme_icon('ticket')" required />

            <x-textarea label="توضیحات" wire:model="description" :icon="theme_icon('note')" rows="3" />

            <x-select
                label="اولویت"
                wire:model="priority"
                :options="$createPriorityOptions"
                option-value="id"
                option-label="name"
                :icon="theme_icon('warning')"
                required
            />

            <x-slot:actions>
                <x-button label="انصراف" @click="$wire.showCreateForm = false" />
                <x-button
                    label="ثبت تیکت"
                    type="submit"
                    class="btn-primary"
                    :icon="theme_icon('save')"
                    spinner="create"
                />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
