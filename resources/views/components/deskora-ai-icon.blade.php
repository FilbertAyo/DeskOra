@props([
    'size' => 24,
])

<span
    {{ $attributes->merge([
        'class' => 'deskora-ai-icon',
        'style' => 'width: '.(int) $size.'px; height: '.(int) $size.'px;',
    ]) }}
    aria-hidden="true"
></span>
