<div>
    <x-header title="تخصیص نقش×شرکت" subtitle="یک کاربر می‌تواند در چند شرکت نقش داشته باشد" separator />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-card shadow>
            <x-form wire:submit="assign" class="gap-5">
                <x-select
                    label="کاربر"
                    wire:model.live="userId"
                    :options="$this->users"
                    option-value="id"
                    option-label="full_name"
                    placeholder="انتخاب کاربر"
                    placeholder-value=""
                    required
                />

                <x-select
                    label="شرکت"
                    wire:model="companyId"
                    :options="$this->companies"
                    option-value="id"
                    option-label="name"
                    placeholder="انتخاب شرکت"
                    placeholder-value=""
                    required
                />

                <x-select
                    label="نقش"
                    wire:model="roleId"
                    :options="$this->roles"
                    option-value="id"
                    option-label="display_name"
                    placeholder="انتخاب نقش"
                    placeholder-value=""
                    required
                />

                <x-slot:actions>
                    <x-button label="تخصیص" type="submit" class="btn-primary" :icon="theme_icon('assign-role')" spinner="assign" />
                </x-slot:actions>
            </x-form>
        </x-card>

        <x-card title="نقش‌های فعلی کاربر" shadow>
            <div class="flex flex-col gap-2">
                @forelse($this->currentRoles as $companyRole)
                    <div class="flex justify-between items-center border-b border-base-300 pb-2">
                        <span>{{ $companyRole->company?->name }}</span>
                        <x-badge value="{{ $companyRole->role?->display_name }}" class="badge-primary badge-outline" />
                    </div>
                @empty
                    <span class="text-base-content/50">کاربری انتخاب نشده یا نقشی ندارد.</span>
                @endforelse
            </div>
        </x-card>
    </div>
</div>
