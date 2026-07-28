<div>
    <x-header title="مدیریت کاربران" subtitle="فهرست کاربران، وضعیت و نقش‌های آنان" separator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجوی نام یا ایمیل..." wire:model.live.debounce.400ms="search" :icon="theme_icon('search')" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="کاربر جدید" :icon="theme_icon('add')" class="btn-primary" link="{{ route('users.create') }}" responsive />
            <x-button label="تخصیص نقش" :icon="theme_icon('assign-role')" class="btn-ghost" link="{{ route('users.assign-role') }}" responsive />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table
            :headers="[
                ['key' => 'full_name', 'label' => 'نام'],
                ['key' => 'email', 'label' => 'ایمیل'],
                ['key' => 'is_active', 'label' => 'وضعیت'],
                ['key' => 'roles', 'label' => 'نقش‌ها'],
            ]"
            :rows="$users"
            with-pagination
        >
            @scope('cell_is_active', $user)
                @if($user->is_active)
                    <x-badge value="فعال" class="badge-success" />
                @else
                    <x-badge value="غیرفعال" class="badge-error" />
                @endif
            @endscope

            @scope('cell_roles', $user)
                <div class="flex flex-wrap gap-1">
                    @forelse($user->companyRoles as $companyRole)
                        <x-badge value="{{ $companyRole->company?->name }}: {{ $companyRole->role?->display_name }}" class="badge-ghost badge-sm" />
                    @empty
                        <span class="text-base-content/50">بدون نقش</span>
                    @endforelse
                </div>
            @endscope

            @scope('actions', $user)
                <x-button
                    :icon="$user->is_active ? theme_icon('deactivate') : theme_icon('activate')"
                    :tooltip-left="$user->is_active ? 'غیرفعال‌سازی' : 'فعال‌سازی'"
                    class="btn-circle btn-ghost btn-sm"
                    wire:click="toggleActive('{{ $user->id }}')"
                    wire:confirm="آیا از تغییر وضعیت این کاربر مطمئن هستید؟"
                    spinner="toggleActive('{{ $user->id }}')"
                />
            @endscope
        </x-table>
    </x-card>
</div>
