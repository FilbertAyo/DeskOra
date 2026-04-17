    @if($reviewerPresentation ?? false)
        @can('reject', $document)
        <div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-labelledby="rejectDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('documents.reject', $document->id) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectDocumentModalLabel">
                                Return {{ $document->type_name }} to Draft
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-2">
                                Provide a clear reason so staff can update and resubmit correctly.
                            </p>
                            <label for="rejectReason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea
                                class="form-control @error('reason') is-invalid @enderror"
                                id="rejectReason"
                                name="reason"
                                rows="4"
                                maxlength="2000"
                                required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="ti ti-arrow-back-up me-1"></i> Return to Draft
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($errors->has('reason'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const rejectModalEl = document.getElementById('rejectDocumentModal');
                    if (rejectModalEl && window.bootstrap && bootstrap.Modal) {
                        const rejectModal = new bootstrap.Modal(rejectModalEl);
                        rejectModal.show();
                    }
                });
            </script>
        @endif
        @endcan
    @endif
