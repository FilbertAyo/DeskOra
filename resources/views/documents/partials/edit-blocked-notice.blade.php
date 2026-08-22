@if($document->isDraft() && ! $document->canBeEdited() && ($editBlockedReason = $document->editBlockedReason()))
    <div class="alert alert-warning py-2 px-3 mb-0 mt-2 small text-start" role="alert">
        <i class="ti ti-lock me-1"></i>{{ $editBlockedReason }}
    </div>
@endif
