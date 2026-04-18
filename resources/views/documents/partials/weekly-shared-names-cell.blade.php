@if($document->sharedUsers->isEmpty())
    <span class="text-muted">—</span>
@else
    <span class="small text-body-secondary">{{ $document->sharedUsers->pluck('name')->join(', ') }}</span>
@endif
