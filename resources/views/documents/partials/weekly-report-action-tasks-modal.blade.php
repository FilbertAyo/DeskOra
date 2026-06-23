@once('weekly-report-action-tasks-modal')
<div class="modal fade" id="weeklyReportActionTaskModal" tabindex="-1" aria-labelledby="weeklyReportActionTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="weeklyReportActionTaskForm" data-action-template="{{ route('action-logs.quick-update', ['document' => '__DOCUMENT__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="weeklyReportActionTaskModalLabel"><i class="ti ti-bolt me-2"></i>Quick Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_uid" id="weeklyReportQuickTaskUid" value="">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Task</label>
                        <div class="border rounded bg-light px-2 py-2 small text-dark" id="weeklyReportQuickTaskLabel"></div>
                        <small class="text-muted" id="weeklyReportQuickTaskPeriod"></small>
                    </div>
                    <div class="mb-3">
                        <label for="weeklyReportQuickTaskProgress" class="form-label">Progress status <span class="text-danger">*</span></label>
                        <select class="form-select" name="progress" id="weeklyReportQuickTaskProgress" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Done">Done</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="weeklyReportQuickTaskNotes" class="form-label">Progress notes <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="notes_progress" id="weeklyReportQuickTaskNotes" rows="4" maxlength="4000" required placeholder="What did you do this week? Any blockers or context?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="weeklyReportQuickTaskSaveBtn">
                        <i class="ti ti-check me-1"></i>Save update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function initWeeklyReportActionTaskModal() {
        const modalEl = document.getElementById('weeklyReportActionTaskModal');
        const form = document.getElementById('weeklyReportActionTaskForm');
        if (!modalEl || !form || form.dataset.wired === '1') {
            return;
        }

        const bootstrapApi = window.bootstrap;
        if (!bootstrapApi || !bootstrapApi.Modal) {
            return;
        }

        form.dataset.wired = '1';

        const actionTemplate = form.getAttribute('data-action-template') || '';
        const labelEl = document.getElementById('weeklyReportQuickTaskLabel');
        const periodEl = document.getElementById('weeklyReportQuickTaskPeriod');
        const uidEl = document.getElementById('weeklyReportQuickTaskUid');
        const progressEl = document.getElementById('weeklyReportQuickTaskProgress');
        const notesEl = document.getElementById('weeklyReportQuickTaskNotes');
        const saveBtn = document.getElementById('weeklyReportQuickTaskSaveBtn');

        document.querySelectorAll('.js-weekly-report-quick-update-task').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const documentId = btn.getAttribute('data-document-id') || '';
                form.setAttribute('action', actionTemplate.replace('__DOCUMENT__', documentId));
                if (labelEl) {
                    labelEl.textContent = btn.getAttribute('data-task') || 'Task';
                }
                if (periodEl) {
                    periodEl.textContent = btn.getAttribute('data-period') || '';
                }
                if (uidEl) {
                    uidEl.value = btn.getAttribute('data-task-uid') || '';
                }
                if (progressEl) {
                    progressEl.value = btn.getAttribute('data-progress') || 'Not Started';
                }
                if (notesEl) {
                    notesEl.value = btn.getAttribute('data-notes') || '';
                }

                bootstrapApi.Modal.getOrCreateInstance(modalEl).show();
            });
        });

        form.addEventListener('submit', function (e) {
            if (notesEl && !notesEl.value.trim()) {
                e.preventDefault();
                notesEl.focus();
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="ti ti-loader me-1"></i>Saving...';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWeeklyReportActionTaskModal);
    } else {
        initWeeklyReportActionTaskModal();
    }
})();
</script>
@endpush
@endonce
