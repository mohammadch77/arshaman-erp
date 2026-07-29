@props(['field', 'label' => null, 'required' => false, 'icon' => null, 'year' => null, 'month' => null])

@php
    $yearOptions = \App\Support\Jalali::yearOptions();
    $monthOptions = \App\Support\Jalali::monthOptions();
    $dayOptions = \App\Support\Jalali::dayOptions($year, $month);
@endphp

<fieldset class="fieldset py-0">
    @if($label)
        <legend class="fieldset-legend mb-0.5">
            {{ $label }}
            @if($required)
                <span class="text-error">*</span>
            @endif
        </legend>
    @endif

    <div class="grid grid-cols-3 gap-2">
        <x-select
            placeholder="سال"
            placeholder-value=""
            wire:model.live="jalaliParts.{{ $field }}.year"
            :options="$yearOptions"
            option-value="id"
            option-label="name"
        />
        <x-select
            placeholder="ماه"
            placeholder-value=""
            wire:model.live="jalaliParts.{{ $field }}.month"
            :options="$monthOptions"
            option-value="id"
            option-label="name"
        />
        <x-select
            placeholder="روز"
            placeholder-value=""
            wire:model.live="jalaliParts.{{ $field }}.day"
            :options="$dayOptions"
            option-value="id"
            option-label="name"
        />
    </div>

    @error($field)
        <div class="text-error">{{ $message }}</div>
    @enderror
</fieldset>
