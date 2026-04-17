<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><i class="ti ti-edit me-2"></i>Edit Action Log</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('action-logs.index') }}">Action Logs</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
                            </ul>
                        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('action-logs.show', $document->id) }}" class="btn btn-sm btn-info text-white">
                                <i class="ti ti-eye me-1"></i> View Action Log
                            </a>
                          
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

 

    @php
        $content = is_array($document->content) ? $document->content : [];
        $taskRows = old('content.tasks', $content['tasks'] ?? []);
        if (! is_array($taskRows) || empty($taskRows)) {
            $taskRows = [[
                'task_uid' => 'task_'.uniqid(),
                'task_no' => 1,
                'task' => '',
                'responsible_user_ids' => [],
                'progress' => 'Not Started',
                'due_date' => '',
                'compilation_date' => '',
                'notes_progress' => '',
                'status_reason' => '',
            ]];
        }
        $tasksForJson = [];
        foreach ($taskRows as $i => $row) {
            $row = is_array($row) ? $row : [];
            $selectedIds = old("content.tasks.$i.responsible_user_ids", $row['responsible_user_ids'] ?? []);
            if (! is_array($selectedIds)) {
                $selectedIds = [];
            }
            $isAssigned = in_array((string) auth()->id(), array_map('strval', $selectedIds), true);
            $canEditRow = $isManager || $isAssigned;
            $tasksForJson[] = [
                'task_uid' => (string) ($row['task_uid'] ?? ''),
                'task' => (string) ($row['task'] ?? ''),
                'responsible_user_ids' => array_values(array_map('strval', $selectedIds)),
                'progress' => (string) ($row['progress'] ?? 'Not Started'),
                'due_date' => $row['due_date'] ? (string) $row['due_date'] : '',
                'compilation_date' => $row['compilation_date'] ? (string) $row['compilation_date'] : '',
                'notes_progress' => (string) ($row['notes_progress'] ?? ''),
                'status_reason' => (string) ($row['status_reason'] ?? ''),
                'can_edit' => $canEditRow,
            ];
        }
    @endphp

    <script type="application/json" id="action-log-initial-tasks">{!! json_encode($tasksForJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('action-logs.update', $document->id) }}" id="editActionLogForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" id="hiddenAction" value="draft">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            Edit Action Log
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <div class="input-group">
                                       
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->state_name }}"
                                            disabled>
                                    </div>
                               </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label class="form-label">Created</label>
                                    <div class="input-group">
                                      
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->created_at->format('M d, Y h:i A') }}"
                                            disabled>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <div class="form-group">
                                    <label for="period_id" class="form-label">
                                        Work Period <span class="text-danger">*</span>
                                    </label>
                                    <input type="hidden" name="period_id" value="{{ old('period_id', $document->period_id ?? '') }}">
                                    <div class="input-group">
                                       
                                        <select class="form-control @error('period_id') is-invalid @enderror"
                                            id="period_id"
                                            aria-hidden="true"
                                            tabindex="-1"
                                            disabled>
                                            <option value="">-- Select Work Period --</option>
                                            @foreach($workPeriods as $workPeriod)
                                                <option value="{{ $workPeriod->id }}"
                                                    {{ old('period_id', $document->period_id ?? '') == $workPeriod->id ? 'selected' : '' }}>
                                                    Week {{ $workPeriod->week_number }} of {{ $workPeriod->year }}
                                                    ({{ \Carbon\Carbon::parse($workPeriod->week_start_date)->format('M d') }} -
                                                     {{ \Carbon\Carbon::parse($workPeriod->week_end_date)->format('M d, Y') }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('period_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                              </div>
                            </div>
                        </div>

                        <div class="card shadow-none border mt-2">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-0"><i class="ti ti-list-check me-2"></i>Action Log Tasks</h6>
                               </div>
                                @if($isManager)
                                    <button type="button" class="btn btn-sm btn-primary" id="addTaskRowBtn" data-bs-toggle="modal" data-bs-target="#actionLogTaskModal">
                                        <i class="ti ti-plus me-1"></i> Add task
                                    </button>
                                @endif
                            </div>
                            <div class="card-body">
                                @if(!$isManager)
                                    <div class="alert alert-info mb-3 py-2">
                                        You can update only <strong>your assigned</strong> tasks. Open a task with <strong>Edit</strong> to change progress, dates, and notes.
                                    </div>
                                @endif
                                <div id="taskListDisplay" class="list-group list-group-flush border rounded"></div>
                                <p id="taskListEmpty" class="text-muted mb-0 d-none">No tasks on this log.</p>
                                <div id="taskRowsBody" class="d-none" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('action-logs.show', $document->id) }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <div class="btn-group">
                                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" id="saveDraftBtn">
                                    <i class="ti ti-save me-1"></i> Save Draft
                                </button>
                                @if($isManager)
                                    <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="ti ti-send me-1"></i> Submit
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <select id="actionLogUserOptionsSource" class="d-none" aria-hidden="true" tabindex="-1">
        @foreach(($teamUsers ?? collect()) as $teamUser)
            <option value="{{ $teamUser->id }}">{{ $teamUser->name }}</option>
        @endforeach
    </select>
    <input type="hidden" id="actionLogIsManagerFlag" value="{{ $isManager ? '1' : '0' }}">

    @include('action-logs.partials.task-modal', ['modalTitle' => 'Task'])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isManager = document.getElementById('actionLogIsManagerFlag').value === '1';
            const form = document.getElementById('editActionLogForm');
            const submitBtn = document.getElementById('submitBtn');
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const hiddenActionInput = document.getElementById('hiddenAction');
            const taskRowsBody = document.getElementById('taskRowsBody');
            const taskListDisplay = document.getElementById('taskListDisplay');
            const taskListEmpty = document.getElementById('taskListEmpty');
            const addTaskRowBtn = document.getElementById('addTaskRowBtn');
            const modalEl = document.getElementById('actionLogTaskModal');
            const modalTitle = document.getElementById('actionLogTaskModalLabel');
            const modalSaveBtn = document.getElementById('actionLogTaskModalSave');
            const userOptionsSource = document.getElementById('actionLogUserOptionsSource');
            const modalResponsible = document.getElementById('actionLogModalResponsible');

            const progressOptionsManager = ['Not Started', 'In Progress', 'Done', 'Cancelled'];
            const progressOptionsStaff = ['Not Started', 'In Progress', 'Done'];

            let tasks = [];
            let editingIndex = null;

            function genUid() {
                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return 'task_' + window.crypto.randomUUID();
                }
                return 'task_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
            }

            function defaultTask() {
                return {
                    task_uid: genUid(),
                    task: '',
                    responsible_user_ids: [],
                    progress: 'Not Started',
                    due_date: '',
                    compilation_date: '',
                    notes_progress: '',
                    status_reason: '',
                    can_edit: isManager,
                };
            }

            function loadInitialTasks() {
                const el = document.getElementById('action-log-initial-tasks');
                if (!el) {
                    tasks = [defaultTask()];
                    return;
                }
                try {
                    const parsed = JSON.parse(el.textContent);
                    tasks = Array.isArray(parsed) && parsed.length ? parsed.map(normalizeTask) : [defaultTask()];
                } catch (e) {
                    tasks = [defaultTask()];
                }
            }

            function normalizeTask(t) {
                return {
                    task_uid: String(t.task_uid || ''),
                    task: String(t.task || ''),
                    responsible_user_ids: Array.isArray(t.responsible_user_ids) ? t.responsible_user_ids.map(String) : [],
                    progress: String(t.progress || 'Not Started'),
                    due_date: t.due_date ? String(t.due_date) : '',
                    compilation_date: t.compilation_date ? String(t.compilation_date) : '',
                    notes_progress: String(t.notes_progress || ''),
                    status_reason: String(t.status_reason || ''),
                    can_edit: t.can_edit === true,
                };
            }

            function fillProgressSelect(selected) {
                const sel = document.getElementById('actionLogModalProgress');
                const opts = isManager ? progressOptionsManager : progressOptionsStaff;
                sel.innerHTML = '';
                if (!isManager && selected && opts.indexOf(selected) === -1) {
                    const o = document.createElement('option');
                    o.value = selected;
                    o.textContent = selected + ' (current)';
                    o.disabled = true;
                    o.selected = true;
                    sel.appendChild(o);
                }
                opts.forEach(function(label) {
                    const o = document.createElement('option');
                    o.value = label;
                    o.textContent = label;
                    if (label === selected) {
                        o.selected = true;
                    }
                    sel.appendChild(o);
                });
            }

            function cloneUserOptionsIntoModal(selectedIds) {
                modalResponsible.innerHTML = userOptionsSource.innerHTML;
                const set = new Set((selectedIds || []).map(String));
                Array.from(modalResponsible.options).forEach(function(opt) {
                    opt.selected = set.has(String(opt.value));
                });
            }

            function setManagerOnlyVisible(show) {
                document.querySelectorAll('.action-log-modal-manager-only').forEach(function(el) {
                    el.classList.toggle('d-none', !show);
                });
                const taskField = document.querySelector('.action-log-modal-field-task');
                const ta = document.getElementById('actionLogModalTask');
                const labelHtml = 'What needs to be done? <span class="text-danger">*</span>';
                if (taskField) {
                    const lbl = taskField.querySelector('.form-label');
                    if (!show) {
                        ta.readOnly = true;
                        lbl.textContent = 'Task (read only)';
                    } else {
                        ta.readOnly = false;
                        lbl.innerHTML = labelHtml;
                    }
                }
            }

            function clearModal() {
                document.getElementById('actionLogModalTaskUid').value = '';
                document.getElementById('actionLogModalTask').value = '';
                document.getElementById('actionLogModalDueDate').value = '';
                document.getElementById('actionLogModalCompilationDate').value = '';
                document.getElementById('actionLogModalNotes').value = '';
                document.getElementById('actionLogModalStatusReason').value = '';
                fillProgressSelect('Not Started');
                cloneUserOptionsIntoModal([]);
            }

            function readModalManager() {
                return {
                    task_uid: document.getElementById('actionLogModalTaskUid').value || genUid(),
                    task: document.getElementById('actionLogModalTask').value,
                    responsible_user_ids: Array.from(modalResponsible.selectedOptions).map(function(o) { return o.value; }),
                    progress: document.getElementById('actionLogModalProgress').value,
                    due_date: document.getElementById('actionLogModalDueDate').value,
                    compilation_date: document.getElementById('actionLogModalCompilationDate').value,
                    notes_progress: document.getElementById('actionLogModalNotes').value,
                    status_reason: document.getElementById('actionLogModalStatusReason').value,
                };
            }

            function readModalStaffMerge(prev) {
                const next = Object.assign({}, prev);
                next.progress = document.getElementById('actionLogModalProgress').value;
                next.due_date = document.getElementById('actionLogModalDueDate').value;
                next.compilation_date = document.getElementById('actionLogModalCompilationDate').value;
                next.notes_progress = document.getElementById('actionLogModalNotes').value;
                return next;
            }

            function fillModal(task) {
                document.getElementById('actionLogModalTaskUid').value = task.task_uid || '';
                document.getElementById('actionLogModalTask').value = task.task || '';
                document.getElementById('actionLogModalDueDate').value = task.due_date || '';
                document.getElementById('actionLogModalCompilationDate').value = task.compilation_date || '';
                document.getElementById('actionLogModalNotes').value = task.notes_progress || '';
                document.getElementById('actionLogModalStatusReason').value = task.status_reason || '';
                fillProgressSelect(task.progress || 'Not Started');
                cloneUserOptionsIntoModal(task.responsible_user_ids || []);
            }

            function showModal() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
            }

            function hideModal() {
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
            }

            function syncPayload() {
                taskRowsBody.innerHTML = '';
                tasks.forEach(function(task, i) {
                    if (!task.task_uid) {
                        task.task_uid = genUid();
                    }
                    const uid = document.createElement('input');
                    uid.type = 'hidden';
                    uid.name = 'content[tasks][' + i + '][task_uid]';
                    uid.value = task.task_uid;
                    taskRowsBody.appendChild(uid);

                    const ta = document.createElement('textarea');
                    ta.name = 'content[tasks][' + i + '][task]';
                    ta.className = 'd-none';
                    ta.textContent = task.task || '';
                    taskRowsBody.appendChild(ta);

                    (task.responsible_user_ids || []).forEach(function(rid) {
                        const hi = document.createElement('input');
                        hi.type = 'hidden';
                        hi.name = 'content[tasks][' + i + '][responsible_user_ids][]';
                        hi.value = rid;
                        taskRowsBody.appendChild(hi);
                    });

                    ['progress', 'due_date', 'compilation_date', 'notes_progress', 'status_reason'].forEach(function(key) {
                        const inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'content[tasks][' + i + '][' + key + ']';
                        inp.value = task[key] != null ? String(task[key]) : '';
                        taskRowsBody.appendChild(inp);
                    });
                });
            }

            function taskPreviewText(task) {
                const t = (task.task || '').trim();
                if (!t) {
                    return '(No description yet)';
                }
                return t.length > 140 ? t.slice(0, 137) + '…' : t;
            }

            function renderTaskList() {
                taskListDisplay.innerHTML = '';
                if (tasks.length === 0) {
                    taskListEmpty.classList.remove('d-none');
                    syncPayload();
                    return;
                }
                taskListEmpty.classList.add('d-none');
                tasks.forEach(function(task, index) {
                    const item = document.createElement('div');
                    item.className = 'list-group-item list-group-item-action flex-column align-items-stretch py-3';
                    const header = document.createElement('div');
                    header.className = 'd-flex w-100 justify-content-between align-items-start gap-2';
                    const left = document.createElement('div');
                    left.className = 'flex-grow-1 min-w-0';
                    const title = document.createElement('div');
                    title.className = 'font-weight-bold text-primary mb-1';
                    title.textContent = 'Task ' + (index + 1);
                    const preview = document.createElement('div');
                    preview.className = 'text-muted small text-break';
                    preview.textContent = taskPreviewText(task);
                    const meta = document.createElement('div');
                    meta.className = 'mt-2';
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-secondary';
                    badge.textContent = task.progress || 'Not Started';
                    meta.appendChild(badge);
                    if (!task.can_edit) {
                        const lock = document.createElement('span');
                        lock.className = 'badge bg-light text-dark border ml-1';
                        lock.textContent = 'View only';
                        meta.appendChild(lock);
                    }
                    left.appendChild(title);
                    left.appendChild(preview);
                    left.appendChild(meta);
                    const actions = document.createElement('div');
                    actions.className = 'btn-group btn-group-sm flex-shrink-0';
                    if (task.can_edit) {
                        const editBtn = document.createElement('button');
                        editBtn.type = 'button';
                        editBtn.className = 'btn btn-outline-primary js-edit-task';
                        editBtn.setAttribute('data-index', String(index));
                        editBtn.innerHTML = '<i class="ti ti-edit"></i> Edit';
                        actions.appendChild(editBtn);
                    }
                    if (isManager) {
                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.className = 'btn btn-outline-danger js-remove-task';
                        delBtn.setAttribute('data-index', String(index));
                        delBtn.innerHTML = '<i class="ti ti-trash"></i>';
                        actions.appendChild(delBtn);
                    }
                    header.appendChild(left);
                    header.appendChild(actions);
                    item.appendChild(header);
                    taskListDisplay.appendChild(item);
                });
                syncPayload();
            }

            modalEl.addEventListener('show.bs.modal', function(ev) {
                const trigger = ev.relatedTarget;
                if (trigger && trigger.id === 'addTaskRowBtn') {
                    editingIndex = null;
                    modalTitle.textContent = 'Add task';
                    setManagerOnlyVisible(true);
                    clearModal();
                }
            });

            if (addTaskRowBtn) {
                addTaskRowBtn.addEventListener('click', function() {
                    editingIndex = null;
                    modalTitle.textContent = 'Add task';
                    setManagerOnlyVisible(true);
                    clearModal();
                });
            }

            taskListDisplay.addEventListener('click', function(ev) {
                const editBtn = ev.target.closest('.js-edit-task');
                const delBtn = ev.target.closest('.js-remove-task');
                if (editBtn) {
                    const idx = parseInt(editBtn.getAttribute('data-index'), 10);
                    if (Number.isNaN(idx) || !tasks[idx]) {
                        return;
                    }
                    editingIndex = idx;
                    modalTitle.textContent = 'Edit task ' + (idx + 1);
                    const staffEditing = !isManager && tasks[idx].can_edit;
                    setManagerOnlyVisible(!staffEditing);
                    fillModal(tasks[idx]);
                    showModal();
                    return;
                }
                if (delBtn) {
                    const idx = parseInt(delBtn.getAttribute('data-index'), 10);
                    if (Number.isNaN(idx)) {
                        return;
                    }
                    if (tasks.length <= 1) {
                        alert('You need at least one task on the action log.');
                        return;
                    }
                    if (!confirm('Remove this task from the list?')) {
                        return;
                    }
                    tasks.splice(idx, 1);
                    renderTaskList();
                }
            });

            modalSaveBtn.addEventListener('click', function() {
                if (editingIndex === null) {
                    if (!isManager) {
                        return;
                    }
                    const t = normalizeTask(readModalManager());
                    t.can_edit = true;
                    tasks.push(t);
                } else {
                    const prev = tasks[editingIndex];
                    if (isManager) {
                        const t = normalizeTask(readModalManager());
                        t.can_edit = prev.can_edit;
                        tasks[editingIndex] = t;
                    } else {
                        tasks[editingIndex] = normalizeTask(readModalStaffMerge(prev));
                    }
                }
                renderTaskList();
                hideModal();
            });

            loadInitialTasks();
            renderTaskList();

            if (saveDraftBtn && hiddenActionInput) {
                saveDraftBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'draft';
                });
            }
            if (submitBtn && hiddenActionInput) {
                submitBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'submit';
                });
            }

            form.addEventListener('submit', function(e) {
                syncPayload();
                const clickedButton = e.submitter;
                const action = clickedButton && clickedButton.value ? clickedButton.value : (hiddenActionInput ? hiddenActionInput.value : 'draft');
                if (hiddenActionInput) {
                    hiddenActionInput.value = action;
                }
                if (action === 'submit' && submitBtn) {
                    if (!confirm('Are you sure you want to submit this action log?')) {
                        e.preventDefault();
                        return false;
                    }
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                } else {
                    if (saveDraftBtn) {
                        saveDraftBtn.disabled = true;
                        saveDraftBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Saving...';
                    }
                }
            });
        });
    </script>

    @include('action-logs.partials.task-editor-styles')
</x-app-layout>
