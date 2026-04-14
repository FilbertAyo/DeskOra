@props([
    'compact' => false,
])

@php
    $name = config('branding.product_name');
    $logo = config('branding.product_logo');
    $height = $compact ? 14 : 20;
@endphp

<div
    {{ $attributes->merge([
        'class' => 'd-inline-flex align-items-center gap-2 text-muted',
    ]) }}
    title="{{ $name }}"
>
    <span @class(['mb-0', 'small' => $compact]) style="font-size: {{ $compact ? '0.7rem' : '0.8125rem' }};">
        Powered by
    </span>
    <img
        src="{{ asset($logo) }}"
        alt="{{ $name }}"
        style="height: {{ $height }}px; width: auto; vertical-align: middle;"
    >
</div>
