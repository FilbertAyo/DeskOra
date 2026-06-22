@php
    $greeting = auth()->check()
        ? app(\App\Services\GreetingService::class)->forUser(auth()->user())
        : null;
@endphp

@if($greeting)
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item active" aria-current="page">
            <span class="text-muted">
                <strong>{{ $greeting['salutation'] }}, {{ $greeting['firstName'] }}!</strong>
                {{ $greeting['message'] }}
            </span>
        </li>
    </ul>
@endif
