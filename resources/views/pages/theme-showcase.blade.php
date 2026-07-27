<?php

use Livewire\Component;

new class extends Component {
    public int $price = 1250000;
}; ?>

<div>
    <x-header title="نمایش تم" subtitle="آزمایش رنگ، آیکون، لوگو و راست‌چین بودن" separator />

    <x-card title="کارت نمونه" shadow>
        <div class="flex items-center gap-3">
            <x-icon :name="theme_icon('order')" class="w-6 text-primary" />
            <span>سفارش‌ها</span>
        </div>

        <div class="mt-4">
            قیمت نمونه: <span class="font-bold text-primary">@toman($price)</span>
        </div>

        <x-slot:actions>
            <x-button label="دکمه اصلی" class="btn-primary" :icon="theme_icon('save')" />
        </x-slot:actions>
    </x-card>
</div>
