@php
    $sharedUsers = $document->relationLoaded('effectiveSharedUsers')
        ? $document->getRelation('effectiveSharedUsers')
        : $document->sharedUsers;
@endphp

@if($sharedUsers->isEmpty())
    <span class="text-muted">—</span>
@else
    <span class="small text-body-secondary">{{ $sharedUsers->pluck('name')->join(', ') }}</span>
@endif
