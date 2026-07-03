@props([
    'modalId',
    'formId',
    'triggerClass',
    'teamUsers' => collect(),
    'title' => 'Quick Update',
])

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST"
                id="{{ $formId }}"
                class="js-action-log-quick-update-form"
                data-action-template="{{ route('action-logs.quick-update', ['document' => '__DOCUMENT__']) }}"
                data-trigger-class="{{ $triggerClass }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}Label"><i class="ti ti-bolt me-2"></i>{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_uid" class="js-quick-task-uid" value="">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Task</label>
                        <div class="border rounded bg-light px-2 py-2 small text-dark js-quick-task-label"></div>
                        <small class="text-muted js-quick-task-period"></small>
                    </div>
                    <div class="mb-3">
                        <label for="{{ $formId }}Progress" class="form-label">Progress status <span class="text-danger">*</span></label>
                        <select class="form-select js-quick-task-progress" name="progress" id="{{ $formId }}Progress" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Done">Done</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Reassign">Reassign</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none js-quick-reassign-field">
                        <label for="{{ $formId }}ReassignUser" class="form-label">Reassign to <span class="text-danger">*</span></label>
                        <select class="form-select js-quick-reassign-user" name="reassign_user_id" id="{{ $formId }}ReassignUser">
                            <option value="">Select staff member...</option>
                            @foreach($teamUsers as $teamUser)
                                <option value="{{ $teamUser->id }}">{{ $teamUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="{{ $formId }}Notes" class="form-label js-quick-notes-label">Progress notes <span class="text-danger">*</span></label>
                        <textarea class="form-control js-quick-task-notes" name="notes_progress" id="{{ $formId }}Notes" rows="4" maxlength="4000" required placeholder="What did you do this week? Any blockers or context?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary js-quick-task-save-btn">
                        <i class="ti ti-check me-1"></i>Save update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function toggleReassignField(form) {
                    const progressEl = form.querySelector('.js-quick-task-progress');
                    const reassignField = form.querySelector('.js-quick-reassign-field');
                    const reassignUserEl = form.querySelector('.js-quick-reassign-user');
                    const notesLabel = form.querySelector('.js-quick-notes-label');
                    const isReassign = progressEl && progressEl.value === 'Reassign';

                    if (reassignField) {
                        reassignField.classList.toggle('d-none', !isReassign);
                    }
                    if (reassignUserEl) {
                        reassignUserEl.required = isReassign;
                        if (!isReassign) {
                            reassignUserEl.value = '';
                        }
                    }
                    if (notesLabel) {
                        notesLabel.innerHTML = isReassign
                            ? 'Reason for reassignment <span class="text-danger">*</span>'
                            : 'Progress notes <span class="text-danger">*</span>';
                    }
                }

                function initActionLogQuickUpdateForm(form) {
                    if (!form || form.dataset.wired === '1') {
                        return;
                    }

                    const bootstrapApi = window.bootstrap;
                    if (!bootstrapApi || !bootstrapApi.Modal) {
                        return;
                    }

                    form.dataset.wired = '1';

                    const modalEl = form.closest('.modal');
                    const actionTemplate = form.getAttribute('data-action-template') || '';
                    const triggerClass = form.getAttribute('data-trigger-class') || 'js-quick-update-task';
                    const labelEl = form.querySelector('.js-quick-task-label');
                    const periodEl = form.querySelector('.js-quick-task-period');
                    const uidEl = form.querySelector('.js-quick-task-uid');
                    const progressEl = form.querySelector('.js-quick-task-progress');
                    const notesEl = form.querySelector('.js-quick-task-notes');
                    const saveBtn = form.querySelector('.js-quick-task-save-btn');

                    document.querySelectorAll('.' + triggerClass).forEach(function (btn) {
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

                            toggleReassignField(form);

                            if (modalEl) {
                                bootstrapApi.Modal.getOrCreateInstance(modalEl).show();
                            }
                        });
                    });

                    if (progressEl) {
                        progressEl.addEventListener('change', function () {
                            toggleReassignField(form);
                        });
                    }

                    form.addEventListener('submit', function (e) {
                        const isReassign = progressEl && progressEl.value === 'Reassign';
                        const reassignUserEl = form.querySelector('.js-quick-reassign-user');

                        if (notesEl && !notesEl.value.trim()) {
                            e.preventDefault();
                            notesEl.focus();
                            return;
                        }

                        if (isReassign && reassignUserEl && !reassignUserEl.value) {
                            e.preventDefault();
                            reassignUserEl.focus();
                            return;
                        }

                        if (saveBtn) {
                            saveBtn.disabled = true;
                            saveBtn.innerHTML = '<i class="ti ti-loader me-1"></i>Saving...';
                        }
                    });
                }

                function initAllActionLogQuickUpdateForms() {
                    document.querySelectorAll('.js-action-log-quick-update-form').forEach(initActionLogQuickUpdateForm);
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initAllActionLogQuickUpdateForms);
                } else {
                    initAllActionLogQuickUpdateForms();
                }
            })();
        </script>
    @endpush
@endonce
