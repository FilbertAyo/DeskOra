    @if($document->isReturnedForRevision() && $document->latestRejectionReason())
        <div class="alert alert-warning border mb-4">
            <h6 class="mb-2">
                <i class="ti ti-alert-triangle me-1"></i> Review Update
            </h6>
            <p class="mb-0">{{ $document->latestRejectionReason() }}</p>
        </div>
    @endif
