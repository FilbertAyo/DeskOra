@if($canAuthorizeDownload)
    <div class="card shadow-none border mb-3">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="ti ti-shield-check me-2"></i>Download authorization</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                {{ $letter->latestDownloadRequest?->requester?->name ?? 'A staff member' }}
                requested permission to download this letter
                @if($letter->latestDownloadRequest?->created_at)
                    on {{ $letter->latestDownloadRequest->created_at->format('d M Y H:i') }}
                @endif
                .
            </p>
            @if(filled($letter->latestDownloadRequest?->request_note))
                <p class="mb-3"><strong>Request note:</strong> {{ $letter->latestDownloadRequest->request_note }}</p>
            @endif
            <div class="d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('official-letters.authorize-download', $letter) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="ti ti-check me-1"></i> Authorize download
                    </button>
                </form>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#letterDownloadRejectModal">
                    <i class="ti ti-x me-1"></i> Reject
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="letterDownloadRejectModal" tabindex="-1" aria-labelledby="letterDownloadRejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('official-letters.reject-download', $letter) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="letterDownloadRejectModalLabel">Reject download request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">The requester will be notified by email and in their inbox.</p>
                        <label for="letterDownloadRejectNote" class="form-label">Reason (optional)</label>
                        <textarea name="decision_note" id="letterDownloadRejectNote" class="form-control" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
