@php
    $target = $target ?? '';
    $action = $action ?? 'improve';
    $label = $label ?? 'AI Assist';
@endphp

@if(config('services.gemini.api_key'))
    <button
        type="button"
        class="btn btn-sm btn-outline-secondary ai-assist-btn"
        data-target="{{ $target }}"
        data-action="{{ $action }}"
        title="Use AI to help write this section"
    >
        <i class="ti ti-sparkles me-1"></i>{{ $label }}
    </button>
@endif
