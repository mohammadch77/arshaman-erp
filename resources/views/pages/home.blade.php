<?php

use Livewire\Component;

new class extends Component {
    //
}; ?>

<div>
    <x-header title="پیشخوان" subtitle="ماژول Core — Session 1" separator />

    <x-card shadow>
        خوش آمدید، {{ auth()->user()->full_name }}.
        <br>
        سوییچر شرکت و پوسته کامل پیشخوان در Session بعد ساخته می‌شود.
    </x-card>
</div>
