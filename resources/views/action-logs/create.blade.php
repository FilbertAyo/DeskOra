<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Create Action Log</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('action-logs.index') }}">Action Logs</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
            </ul>
        </div>

                        <div class="col-auto d-flex align-items-center gap-2">
            <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-light">
                <i class="ti ti-arrow-left me-1"></i> Back to Action Logs
            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @php
        $taskRows = old('content.tasks', $carryForwardTasks ?? []);
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
            ]];
        }
        $tasksForJson = collect($taskRows)->map(function ($row) {
            $row = is_array($row) ? $row : [];

            return [
                'task_uid' => (string) ($row['task_uid'] ?? ''),
                'task' => (string) ($row['task'] ?? ''),
                'responsible_user_ids' => array_values(array_map('strval', is_array($row['responsible_user_ids'] ?? null) ? $row['responsible_user_ids'] : [])),
                'progress' => (string) ($row['progress'] ?? 'Not Started'),
                'due_date' => $row['due_date'] ? (string) $row['due_date'] : '',
                'compilation_date' => $row['compilation_date'] ? (string) $row['compilation_date'] : '',
                'notes_progress' => (string) ($row['notes_progress'] ?? ''),
                'can_edit' => true,
            ];
        })->values()->all();
        $effectivePeriodId = old('period_id', $defaultPeriodId ?? '');
    @endphp

    <script type="application/json" id="action-log-initial-tasks">{!! json_encode($tasksForJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('action-logs.store') }}" id="createActionLogForm">
                @csrf
                <input type="hidden" name="action" id="hiddenAction" value="draft">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="ti ti-clipboard me-2"></i>Action Log Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="form-group">
                                    <label for="period_id" class="form-label">
                                        Work Period <span class="text-danger">*</span>
                                    </label>
                                    <input type="hidden" name="period_id" value="{{ $effectivePeriodId }}">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control @error('period_id') is-invalid @enderror"
                                            id="period_id"
                                            aria-hidden="true"
                                            tabindex="-1"
                                            disabled>
                                            <option value="">-- Select Work Period --</option>
                                            @foreach($workPeriods as $workPeriod)
                                                <option value="{{ $workPeriod->id }}" 
                                                    {{ $effectivePeriodId == $workPeriod->id ? 'selected' : '' }}>
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
                                    <small class="form-text text-muted">Action logs are tracked for the current week period.</small>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-none border mt-2">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                <h6 class="mb-0"><i class="ti ti-list-check me-2"></i>Action Log Tasks</h6>
                                    <small class="text-muted">Use <strong>Add task</strong> to open a full-screen-friendly form in a modal. Edit or remove tasks from the list below.</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="addTaskRowBtn" data-bs-toggle="modal" data-bs-target="#actionLogTaskModal">
                                    <i class="ti ti-plus me-1"></i> Add task
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="taskListDisplay" class="list-group list-group-flush border rounded"></div>
                                <p id="taskListEmpty" class="text-muted mb-0 d-none">No tasks yet. Click <strong>Add task</strong>.</p>
                                <div id="taskRowsBody" class="d-none" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('action-logs.index') }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <div class="btn-group">
                                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" id="saveDraftBtn">
                                    <i class="ti ti-save me-1"></i> Save Draft
                                </button>
                                <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="ti ti-send me-1"></i> Submit
                                </button>
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

    @include('action-logs.partials.task-modal', ['modalTitle' => 'Add task'])

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createActionLogForm');
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

            const isManager = true;
            const progressOptionsManager = ['Not Started', 'In Progress', 'Done', 'Cancelled'];
            const progressOptionsStaff = ['Not Started', 'In Progress', 'Done', 'Cancelled'];

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
                    can_edit: true,
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
                    can_edit: t.can_edit !== false,
                };
            }

            function fillProgressSelect(selected) {
                const sel = document.getElementById('actionLogModalProgress');
                const opts = isManager ? progressOptionsManager : progressOptionsStaff;
                sel.innerHTML = '';
                opts.forEach(function(label) {
                    const o = document.createElement('option');
                    o.value = label;
                    o.textContent = label;
                    if (label === selected) o.selected = true;
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
                if (!show && taskField) {
                    ta.readOnly = true;
                    taskField.querySelector('.form-label').innerHTML = 'Task (read only)';
                } else if (taskField) {
                    ta.readOnly = false;
                    taskField.querySelector('.form-label').innerHTML = 'What needs to be done? <span class="text-danger">*</span>';
                }
            }

            function clearModal() {
                document.getElementById('actionLogModalTaskUid').value = '';
                document.getElementById('actionLogModalTask').value = '';
                document.getElementById('actionLogModalDueDate').value = '';
                document.getElementById('actionLogModalCompilationDate').value = '';
                document.getElementById('actionLogModalNotes').value = '';
                fillProgressSelect('Not Started');
                cloneUserOptionsIntoModal([]);
            }

            function readModal() {
                return {
                    task_uid: document.getElementById('actionLogModalTaskUid').value || genUid(),
                    task: document.getElementById('actionLogModalTask').value,
                    responsible_user_ids: isManager
                        ? Array.from(modalResponsible.selectedOptions).map(function(o) { return o.value; })
                        : [],
                    progress: document.getElementById('actionLogModalProgress').value,
                    due_date: document.getElementById('actionLogModalDueDate').value,
                    compilation_date: document.getElementById('actionLogModalCompilationDate').value,
                    notes_progress: document.getElementById('actionLogModalNotes').value,
                };
            }

            function fillModal(task) {
                document.getElementById('actionLogModalTaskUid').value = task.task_uid || '';
                document.getElementById('actionLogModalTask').value = task.task || '';
                document.getElementById('actionLogModalDueDate').value = task.due_date || '';
                document.getElementById('actionLogModalCompilationDate').value = task.compilation_date || '';
                document.getElementById('actionLogModalNotes').value = task.notes_progress || '';
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
                    if (!task.task_uid) task.task_uid = genUid();
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

                    ['progress', 'due_date', 'compilation_date', 'notes_progress'].forEach(function(key) {
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
                if (!t) return '(No description yet)';
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
                    badge.className = 'badge bg-light-secondary';
                    badge.textContent = task.progress || 'Not Started';
                    meta.appendChild(badge);
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
                setManagerOnlyVisible(isManager);
                if (trigger && trigger.id === 'addTaskRowBtn') {
                    editingIndex = null;
                    modalTitle.textContent = 'Add task';
                    clearModal();
                }
            });

            taskListDisplay.addEventListener('click', function(ev) {
                const editBtn = ev.target.closest('.js-edit-task');
                const delBtn = ev.target.closest('.js-remove-task');
                if (editBtn) {
                    const idx = parseInt(editBtn.getAttribute('data-index'), 10);
                    if (Number.isNaN(idx) || !tasks[idx]) return;
                    editingIndex = idx;
                    modalTitle.textContent = 'Edit task ' + (idx + 1);
                    setManagerOnlyVisible(isManager);
                    fillModal(tasks[idx]);
                    showModal();
                    return;
                }
                if (delBtn) {
                    const idx = parseInt(delBtn.getAttribute('data-index'), 10);
                    if (Number.isNaN(idx)) return;
                    if (tasks.length <= 1) {
                        alert('You need at least one task on the action log.');
                        return;
                    }
                    if (!confirm('Remove this task from the list?')) return;
                    tasks.splice(idx, 1);
                    renderTaskList();
                }
            });

            modalSaveBtn.addEventListener('click', function() {
                if (editingIndex === null) {
                    const t = normalizeTask(readModal());
                    t.can_edit = true;
                    tasks.push(t);
                } else {
                    const prev = tasks[editingIndex];
                    const t = normalizeTask(readModal());
                    t.can_edit = prev.can_edit !== false;
                    tasks[editingIndex] = t;
                }
                renderTaskList();
                hideModal();
            });

            addTaskRowBtn.addEventListener('click', function() {
                editingIndex = null;
                modalTitle.textContent = 'Add task';
                setManagerOnlyVisible(true);
                clearModal();
            });

            loadInitialTasks();
            setManagerOnlyVisible(true);
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
                if (hiddenActionInput) hiddenActionInput.value = action;
                if (action === 'submit') {
                    if (!confirm('Are you sure you want to submit this action log?')) {
                        e.preventDefault();
                        return false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                    }
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
