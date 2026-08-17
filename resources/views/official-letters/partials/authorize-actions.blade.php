@if($canAuthorizeDownload)
    <div class="alert alert-warning border-warning d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div>
            <div class="fw-semibold mb-1">
                <i class="ti ti-shield-check me-1"></i>Download authorization needed
            </div>
            <div class="small mb-0">
                {{ $letter->latestDownloadRequest?->requester?->name ?? 'A staff member' }}
                requested permission to download this letter
                @if($letter->latestDownloadRequest?->created_at)
                    on {{ $letter->latestDownloadRequest->created_at->format('d M Y H:i') }}
                @endif
                .
                @if(filled($letter->latestDownloadRequest?->request_note))
                    <span class="d-block mt-1"><strong>Note:</strong> {{ $letter->latestDownloadRequest->request_note }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 flex-shrink-0">
            <form method="POST" action="{{ route('official-letters.authorize-download', $letter) }}">
                @csrf
                <button type="submit" class="btn btn-success btn-sm">
                    <i class="ti ti-check me-1"></i> Authorize
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#letterDownloadRejectModal">
                <i class="ti ti-x me-1"></i> Reject
            </button>
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
