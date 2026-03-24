<x-app-layout>
    <div class="row align-items-center mb-3 border-bottom no-gutters">
        <div class="col">
            <ul class="nav nav-tabs border-0" id="myTab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="home-tab" data-toggle="tab" href="#home" role="tab"
                        aria-controls="home" aria-selected="true">
                        <i class="ti ti-clipboard me-1"></i> Create Action Log
                    </a>
                </li>
            </ul>
        </div>
        <div class="col-auto">
            <a href="{{ route('action-logs.index') }}" class="btn btn-sm btn-light">
                <i class="ti ti-arrow-left me-1"></i> Back to Action Logs
            </a>
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
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="period_id" class="form-label">
                                        Work Period <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control @error('period_id') is-invalid @enderror"
                                            name="period_id"
                                            id="period_id"
                                            required>
                                            <option value="">-- Select Work Period --</option>
                                            @foreach($workPeriods as $workPeriod)
                                                <option value="{{ $workPeriod->id }}" 
                                                    {{ old('period_id', $defaultPeriodId ?? '') == $workPeriod->id ? 'selected' : '' }}>
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

                            <!-- Title -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        Title (Optional)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-type"></i>
                                        </span>
                                        <input type="text"
                                            class="form-control @error('title') is-invalid @enderror"
                                            name="title"
                                            id="title"
                                            placeholder="e.g., Action Log - Week 45"
                                            value="{{ old('title') }}">
                                    </div>
                                    @error('title')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Leave blank to auto-generate with week number.</small>
                                </div>
                            </div>
                        </div>

                        @php
                            $taskRows = old('content.tasks', $carryForwardTasks ?? []);
                            if (!is_array($taskRows) || empty($taskRows)) {
                                $taskRows = [[
                                    'task_uid' => 'task_' . uniqid(),
                                    'task_no' => 1,
                                    'task' => '',
                                    'responsible_user_ids' => [],
                                    'progress' => 'Not Started',
                                    'due_date' => '',
                                    'compilation_date' => '',
                                    'notes_progress' => '',
                                ]];
                            }
                        @endphp

                        <div class="card shadow-none border mt-2">
                            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="ti ti-list-check me-2"></i>Action Log Tasks</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addTaskRowBtn">
                                    <i class="ti ti-plus me-1"></i> Add Task
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 70px;">No</th>
                                                <th>Task</th>
                                                <th style="width: 240px;">Responsible Person</th>
                                                <th style="width: 160px;">Progress</th>
                                                <th style="width: 150px;">Due Date</th>
                                                <th style="width: 170px;">Compilation Date</th>
                                                <th>Notes/Progress</th>
                                                <th style="width: 60px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="taskRowsBody">
                                            @foreach($taskRows as $i => $row)
                                                @php
                                                    $row = is_array($row) ? $row : [];
                                                    $selectedIds = old("content.tasks.$i.responsible_user_ids", $row['responsible_user_ids'] ?? []);
                                                    if (!is_array($selectedIds)) $selectedIds = [];
                                                @endphp
                                                <tr>
                                                    <td class="text-center align-middle js-task-row-no">{{ $i + 1 }}</td>
                                                    <td>
                                                        <input type="hidden" name="content[tasks][{{ $i }}][task_uid]" value="{{ old("content.tasks.$i.task_uid", $row['task_uid'] ?? ('task_' . uniqid())) }}">
                                                        <textarea class="form-control @error("content.tasks.$i.task") is-invalid @enderror"
                                                            name="content[tasks][{{ $i }}][task]"
                                                            rows="2"
                                                            placeholder="Task description">{{ old("content.tasks.$i.task", $row['task'] ?? '') }}</textarea>
                                                    </td>
                                                    <td>
                                                        <select class="form-control"
                                                            multiple
                                                            name="content[tasks][{{ $i }}][responsible_user_ids][]">
                                                            @foreach(($teamUsers ?? collect()) as $teamUser)
                                                                <option value="{{ $teamUser->id }}" {{ in_array((string) $teamUser->id, array_map('strval', $selectedIds), true) ? 'selected' : '' }}>
                                                                    {{ $teamUser->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select class="form-control" name="content[tasks][{{ $i }}][progress]">
                                                            @php $currentProgress = old("content.tasks.$i.progress", $row['progress'] ?? 'Not Started'); @endphp
                                                            @foreach(['Not Started', 'In Progress', 'Done'] as $progressOption)
                                                                <option value="{{ $progressOption }}" {{ $currentProgress === $progressOption ? 'selected' : '' }}>
                                                                    {{ $progressOption }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control" name="content[tasks][{{ $i }}][due_date]"
                                                            value="{{ old("content.tasks.$i.due_date", $row['due_date'] ?? '') }}">
                                                    </td>
                                                    <td>
                                                        <input type="date" class="form-control" name="content[tasks][{{ $i }}][compilation_date]"
                                                            value="{{ old("content.tasks.$i.compilation_date", $row['compilation_date'] ?? '') }}">
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control" rows="2"
                                                            name="content[tasks][{{ $i }}][notes_progress]"
                                                            placeholder="Notes and progress update">{{ old("content.tasks.$i.notes_progress", $row['notes_progress'] ?? '') }}</textarea>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-task-row">&times;</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createActionLogForm');
            const submitBtn = document.getElementById('submitBtn');
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const hiddenActionInput = document.getElementById('hiddenAction');
            const taskRowsBody = document.getElementById('taskRowsBody');
            const addTaskRowBtn = document.getElementById('addTaskRowBtn');
            let taskIndex = taskRowsBody ? taskRowsBody.querySelectorAll('tr').length : 0;

            function updateTaskRowNumbers() {
                if (!taskRowsBody) return;
                taskRowsBody.querySelectorAll('.js-task-row-no').forEach((cell, idx) => {
                    cell.textContent = String(idx + 1);
                });
            }

            if (addTaskRowBtn && taskRowsBody) {
                addTaskRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-task-row-no"></td>
                        <td>
                            <input type="hidden" name="content[tasks][${taskIndex}][task_uid]" value="task_${Date.now()}_${taskIndex}">
                            <textarea class="form-control" name="content[tasks][${taskIndex}][task]" rows="2" placeholder="Task description"></textarea>
                        </td>
                        <td>
                            <select class="form-control" multiple name="content[tasks][${taskIndex}][responsible_user_ids][]">
                                ${(window.__actionLogUserOptions || '')}
                            </select>
                        </td>
                        <td>
                            <select class="form-control" name="content[tasks][${taskIndex}][progress]">
                                <option value="Not Started">Not Started</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Done">Done</option>
                            </select>
                        </td>
                        <td><input type="date" class="form-control" name="content[tasks][${taskIndex}][due_date]"></td>
                        <td><input type="date" class="form-control" name="content[tasks][${taskIndex}][compilation_date]"></td>
                        <td><textarea class="form-control" rows="2" name="content[tasks][${taskIndex}][notes_progress]" placeholder="Notes and progress update"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-task-row">&times;</button></td>
                    `;
                    taskRowsBody.appendChild(row);
                    taskIndex++;
                    updateTaskRowNumbers();
                });
            }

            if (taskRowsBody) {
                const firstUserSelect = taskRowsBody.querySelector('select[name$="[responsible_user_ids][]"]');
                window.__actionLogUserOptions = firstUserSelect ? firstUserSelect.innerHTML : '';

                taskRowsBody.addEventListener('click', function(event) {
                    const removeBtn = event.target.closest('.remove-task-row');
                    if (!removeBtn) return;

                    const rows = taskRowsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    removeBtn.closest('tr')?.remove();
                    updateTaskRowNumbers();
                });
            }

            updateTaskRowNumbers();
            
            // Ensure action is set when buttons are clicked
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
                const clickedButton = e.submitter;
                const action = clickedButton?.value || hiddenActionInput?.value || 'draft';
                
                // Ensure the hidden action field is set to the correct value
                if (hiddenActionInput) {
                    hiddenActionInput.value = action;
                }
                
                if (action === 'submit') {
                    if (!confirm('Are you sure you want to submit this action log? Once submitted, it cannot be edited.')) {
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

    <style>
        .spinning {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</x-app-layout>

