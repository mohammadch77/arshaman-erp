<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';

    public bool $drawer = false;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // Clear filters
    public function clear(): void
    {
        $this->reset();
        $this->success('فیلترها پاک شد.', position: 'toast-bottom');
    }

    // Delete action
    public function delete($id): void
    {
        $this->warning("حذف می‌شود #$id", 'این فقط یک نمونه است.', position: 'toast-bottom');
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-1'],
            ['key' => 'name', 'label' => 'نام', 'class' => 'w-64'],
            ['key' => 'age', 'label' => 'سن', 'class' => 'w-20'],
            ['key' => 'email', 'label' => 'ایمیل', 'sortable' => false],
        ];
    }

    /**
     * For demo purpose, this is a static collection.
     *
     * On real projects you do it with Eloquent collections.
     * Please, refer to maryUI docs to see the eloquent examples.
     */
    public function users(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'Mary', 'email' => 'mary@mary-ui.com', 'age' => 23],
            ['id' => 2, 'name' => 'Giovanna', 'email' => 'giovanna@mary-ui.com', 'age' => 7],
            ['id' => 3, 'name' => 'Marina', 'email' => 'marina@mary-ui.com', 'age' => 5],
        ])
            ->sortBy([[...array_values($this->sortBy)]])
            ->when($this->search, function (Collection $collection) {
                return $collection->filter(fn(array $item) => str($item['name'])->contains($this->search, true));
            });
    }

    public function with(): array
    {
        return [
            'users' => $this->users(),
            'headers' => $this->headers()
        ];
    }
}; ?>

<div>
    <!-- HEADER -->
    <x-header title="کاربران" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="جستجو..." wire:model.live.debounce="search" clearable :icon="theme_icon('search')" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="فیلترها" @click="$wire.drawer = true" responsive :icon="theme_icon('filter')" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card shadow>
        <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy">
            @scope('actions', $user)
            <x-button :icon="theme_icon('delete')" wire:click="delete({{ $user['id'] }})" wire:confirm="آیا مطمئن هستید؟" spinner class="btn-ghost btn-sm text-error" />
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="فیلترها" right separator with-close-button class="lg:w-1/3">
        <x-input placeholder="جستجو..." wire:model.live.debounce="search" :icon="theme_icon('search')" @keydown.enter="$wire.drawer = false" />

        <x-slot:actions>
            <x-button label="پاک‌سازی" :icon="theme_icon('clear')" wire:click="clear" spinner />
            <x-button label="تأیید" :icon="theme_icon('save')" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>
