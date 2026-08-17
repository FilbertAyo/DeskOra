@php
    $status = $letter->downloadAuthorizationStatus();
    $labels = [
        'pending' => ['Awaiting download authorization', 'bg-light-warning border border-warning text-warning'],
        'authorized' => ['Download authorized', 'bg-light-success border border-success text-success'],
        'rejected' => ['Download rejected', 'bg-light-danger border border-danger text-danger'],
    ];
@endphp
@if($status && isset($labels[$status]))
    <span class="badge {{ $labels[$status][1] }} {{ $class ?? '' }}">{{ $labels[$status][0] }}</span>
@endif
