@php
    $status = $letter->downloadAuthorizationStatus();
    $labels = [
        'pending' => ['Pending', 'bg-light-warning border border-warning text-warning'],
        'authorized' => ['Authorized', 'bg-light-success border border-success text-success'],
        'rejected' => ['Rejected', 'bg-light-danger border border-danger text-danger'],
    ];
@endphp
@if($status && isset($labels[$status]))
    <span class="badge {{ $labels[$status][1] }} {{ $class ?? '' }}">{{ $labels[$status][0] }}</span>
@endif
