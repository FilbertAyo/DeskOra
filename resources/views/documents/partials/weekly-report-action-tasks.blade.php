@php
    $actionLogProgress = $actionLogProgress ?? ['activeTasks' => []];
    $activeActionTasks = collect($actionLogProgress['activeTasks'] ?? [])
        ->filter(function ($task) {
            if (! is_array($task)) {
                return false;
            }

            $latestProgress = trim((string) ($task['latest_progress'] ?? 'Not Started'));

            return ! in_array($latestProgress, ['Done', 'Cancelled'], true);
        })
        ->values();
    $pendingActionTasks = $activeActionTasks->filter(fn ($task) => ! empty($task['needs_update']))->values();
    $pendingActionTaskCount = $pendingActionTasks->count();
@endphp

<div class="col-12 mb-3" id="weeklyReportActionTasksSection">
    <div class="card border-primary">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="ti ti-bolt me-2"></i>7. Action Tasks (assigned to you)
                @if($pendingActionTaskCount > 0)
                    <span class="badge bg-light-warning border border-warning text-dark ms-1">{{ $pendingActionTaskCount }} pending</span>
                @endif
            </h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Update progress on action tasks assigned to you. You must update all pending tasks before submitting this weekly report.
                You can still save a draft without updating them.
            </p>

            @if($pendingActionTaskCount > 0)
                <div class="alert alert-warning py-2 mb-3" role="alert">
                    <i class="ti ti-alert-triangle me-1"></i>
                    {{ $pendingActionTaskCount === 1 ? '1 action task still needs' : $pendingActionTaskCount . ' action tasks still need' }}
                    an update before you can submit this report.
                </div>
            @endif

            @if($activeActionTasks->isNotEmpty())
                <div class="list-group list-group-flush border rounded">
                    @foreach($activeActionTasks as $task)
                        @php
                            $tProgress = $task['latest_progress'] ?? 'Not Started';
                            $badgeClass = match($tProgress) {
                                'Done' => 'bg-light-success border border-success',
                                'Cancelled' => 'bg-light-danger border border-danger',
                                'In Progress' => 'bg-light-info border border-info',
                                default => 'bg-light-secondary border border-secondary',
                            };
                        @endphp
                        <div class="list-group-item px-2 py-2">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="flex-grow-1 min-w-0">
                                    <div class="small text-dark text-break">{{ $task['task'] ?? 'Task' }}</div>
                                    <div class="mt-1">
                                        <span class="badge {{ $badgeClass }}">{{ $tProgress }}</span>
                                        @if(!empty($task['period_label']))
                                            <span class="badge bg-light-light border border-light text-dark">{{ $task['period_label'] }}</span>
                                        @endif
                                        @if(!empty($task['needs_update']))
                                            <span class="badge bg-light-warning border border-warning text-dark">Needs update</span>
                                        @endif
                                    </div>
                                    @if(!empty($task['needs_update']) && !empty($task['carried_from_period_label']))
                                        <div class="text-muted small mt-1">
                                            From {{ $task['carried_from_period_label'] }} — no update this week yet.
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0">
                                    @if(!empty($task['editable']))
                                        <button type="button"
                                            class="btn btn-sm btn-primary js-quick-update-task @if(!empty($task['needs_update'])) js-pending-action-task @endif"
                                            data-task="{{ $task['task'] ?? 'Task' }}"
                                            data-task-uid="{{ $task['task_uid'] }}"
                                            data-document-id="{{ $task['document_id'] }}"
                                            data-progress="{{ $tProgress }}"
                                            data-notes="{{ $task['latest_notes'] ?? '' }}"
                                            data-period="{{ $task['period_label'] ?? '' }}">
                                            Update
                                        </button>
                                    @elseif(!empty($task['document_id']))
                                        <a href="{{ route('action-logs.show', $task['document_id']) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-3">
                    <i class="ti ti-checkup-list fs-3 text-muted"></i>
                    <p class="text-muted mb-0 mt-2 small">No action tasks need your attention right now.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="quickActionTaskModal" tabindex="-1" aria-labelledby="quickActionTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="quickActionTaskForm" data-action-template="{{ route('action-logs.quick-update', ['document' => '__DOCUMENT__']) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="quickActionTaskModalLabel"><i class="ti ti-bolt me-2"></i>Quick Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="task_uid" id="quickTaskUid" value="">
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Task</label>
                        <div class="border rounded bg-light px-2 py-2 small text-dark" id="quickTaskLabel"></div>
                        <small class="text-muted" id="quickTaskPeriod"></small>
                    </div>
                    <div class="mb-3">
                        <label for="quickTaskProgress" class="form-label">Progress status <span class="text-danger">*</span></label>
                        <select class="form-select" name="progress" id="quickTaskProgress" required>
                            <option value="Not Started">Not Started</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Done">Done</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label for="quickTaskNotes" class="form-label">Progress notes</label>
                        <textarea class="form-control" name="notes_progress" id="quickTaskNotes" rows="4" maxlength="4000" placeholder="What did you do this week? Any blockers or context?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="quickTaskSaveBtn">
                        <i class="ti ti-check me-1"></i>Save update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('quickActionTaskModal');
        if (!modalEl) {
            return;
        }
        const form = document.getElementById('quickActionTaskForm');
        const actionTemplate = form.getAttribute('data-action-template');
        const labelEl = document.getElementById('quickTaskLabel');
        const periodEl = document.getElementById('quickTaskPeriod');
        const uidEl = document.getElementById('quickTaskUid');
        const progressEl = document.getElementById('quickTaskProgress');
        const notesEl = document.getElementById('quickTaskNotes');
        const saveBtn = document.getElementById('quickTaskSaveBtn');

        document.querySelectorAll('.js-quick-update-task').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const documentId = btn.getAttribute('data-document-id') || '';
                form.setAttribute('action', actionTemplate.replace('__DOCUMENT__', documentId));
                labelEl.textContent = btn.getAttribute('data-task') || 'Task';
                periodEl.textContent = btn.getAttribute('data-period') || '';
                uidEl.value = btn.getAttribute('data-task-uid') || '';
                progressEl.value = btn.getAttribute('data-progress') || 'Not Started';
                notesEl.value = btn.getAttribute('data-notes') || '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            });
        });

        form.addEventListener('submit', function () {
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="ti ti-loader me-1"></i>Saving...';
            }
        });
    });
</script>
