<div class="modal fade" id="actionLogTaskModal" tabindex="-1" role="dialog" aria-labelledby="actionLogTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="actionLogTaskModalLabel">{{ $modalTitle ?? 'Task' }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="actionLogModalTaskUid" value="">
                <div class="mb-3 action-log-modal-field-task">
                    <label for="actionLogModalTask" class="form-label">What needs to be done? <span class="text-danger">*</span></label>
                    <textarea class="form-control action-log-textarea-lg" id="actionLogModalTask" rows="6" placeholder="Describe the action clearly (who, what, expected outcome)"></textarea>
                </div>
                <div class="mb-3 action-log-modal-manager-only">
                    <label for="actionLogModalResponsible" class="form-label">Responsible people</label>
                    <small class="d-block text-muted mb-1">Hold Ctrl/Cmd (Windows/Mac) to select more than one.</small>
                    <select class="form-control action-log-select-multi" id="actionLogModalResponsible" multiple size="8"></select>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="actionLogModalProgress" class="form-label">Progress</label>
                        <select class="form-control" id="actionLogModalProgress"></select>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="actionLogModalDueDate" class="form-label">Due date</label>
                        <input type="date" class="form-control" id="actionLogModalDueDate">
                    </div>
                    <div class="col-sm-6 mb-3">
                        <label for="actionLogModalCompilationDate" class="form-label">Compilation date</label>
                        <input type="date" class="form-control" id="actionLogModalCompilationDate">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="actionLogModalNotes" class="form-label">Notes / progress update</label>
                    <textarea class="form-control action-log-textarea-md" id="actionLogModalNotes" rows="4" placeholder="Weekly updates, blockers, or context"></textarea>
                </div>
                <div class="mb-0 action-log-modal-manager-only">
                    <label for="actionLogModalStatusReason" class="form-label">Cancellation reason</label>
                    <small class="d-block text-muted mb-1">Required only if progress is <strong>Cancelled</strong>.</small>
                    <textarea class="form-control action-log-textarea-sm" id="actionLogModalStatusReason" rows="3" placeholder="Why was this task cancelled?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="actionLogTaskModalSave">
                    <i class="ti ti-check me-1"></i> Save task
                </button>
            </div>
        </div>
    </div>
</div>
