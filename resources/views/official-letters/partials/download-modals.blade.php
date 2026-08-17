<div class="modal fade" id="letterDownloadRequestModal" tabindex="-1" aria-labelledby="letterDownloadRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="letterDownloadRequestForm" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="letterDownloadRequestModalLabel">Request download authorization</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        You need permission to download
                        <code id="letterDownloadRequestReference"></code>
                        as a PDF.
                    </p>
                    <p id="letterDownloadRequestRejectedHint" class="text-danger small d-none mb-2">
                        The previous request was rejected. You can request authorization again.
                    </p>
                    <p class="text-muted small">
                        A notification will be sent to staff who can authorize official letter downloads.
                    </p>
                    <div class="mb-0">
                        <label for="letterDownloadRequestNote" class="form-label">Note (optional)</label>
                        <textarea name="request_note"
                                  id="letterDownloadRequestNote"
                                  class="form-control"
                                  rows="3"
                                  maxlength="1000"
                                  placeholder="Add a short reason for this download, if needed."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-send me-1"></i> Request permission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="letterDownloadPendingModal" tabindex="-1" aria-labelledby="letterDownloadPendingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="letterDownloadPendingModalLabel">Awaiting authorization</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">
                    Download authorization for
                    <code id="letterDownloadPendingReference"></code>
                    is already pending. You will be notified when it is reviewed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var requestModal = document.getElementById('letterDownloadRequestModal');
        if (requestModal) {
            requestModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }
                var form = document.getElementById('letterDownloadRequestForm');
                var reference = document.getElementById('letterDownloadRequestReference');
                var rejectedHint = document.getElementById('letterDownloadRequestRejectedHint');
                var note = document.getElementById('letterDownloadRequestNote');
                form.setAttribute('action', button.getAttribute('data-request-url') || '');
                reference.textContent = button.getAttribute('data-letter-reference') || '';
                rejectedHint.classList.toggle('d-none', button.getAttribute('data-download-status') !== 'rejected');
                if (note) {
                    note.value = '';
                }
            });
        }

        var pendingModal = document.getElementById('letterDownloadPendingModal');
        if (pendingModal) {
            pendingModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) {
                    return;
                }
                var reference = document.getElementById('letterDownloadPendingReference');
                reference.textContent = button.getAttribute('data-letter-reference') || '';
            });
        }
    });
</script>
