@props(['size' => 'normal'])

<img
    src="{{ asset('images/theme/'.($size === 'small' ? 'logo-small.svg' : 'logo.svg')) }}"
    alt="آرشامان"
    {{ $attributes->merge(['class' => $size === 'small' ? 'h-6 w-auto' : 'h-8 w-auto']) }}
/>
