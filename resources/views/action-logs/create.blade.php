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
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $taskRows = old('content.tasks', $carryForwardTasks ?? []);
        if (! is_array($taskRows)) {
            $taskRows = [];
        }
        $tasksForJson = collect($taskRows)->map(function ($row) {
            $row = is_array($row) ? $row : [];

            return [
                'task_uid' => (string) ($row['task_uid'] ?? ''),
                'task_no' => (int) ($row['task_no'] ?? 0),
                'task' => (string) ($row['task'] ?? ''),
                'responsible_user_ids' => array_values(array_map('strval', is_array($row['responsible_user_ids'] ?? null) ? $row['responsible_user_ids'] : [])),
                'progress' => (string) ($row['progress'] ?? 'Not Started'),
                'due_date' => $row['due_date'] ? (string) $row['due_date'] : '',
                'compilation_date' => $row['compilation_date'] ? (string) $row['compilation_date'] : '',
                'notes_progress' => (string) ($row['notes_progress'] ?? ''),
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
                                    <small class="text-muted">Tasks save automatically when you add or edit them.</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="addTaskRowBtn" data-bs-toggle="modal" data-bs-target="#actionLogTaskModal">
                                    <i class="ti ti-plus me-1"></i> Add task
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="actionLogAutosaveAlert"></div>
                                <div id="taskTableContainer" class="table-responsive {{ empty($tasksForJson) ? 'd-none' : '' }}">
                                    <table class="table table-bordered table-hover align-middle mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 60px;">No</th>
                                                <th style="min-width: 220px;">Task</th>
                                                <th style="width: 180px;">Responsible</th>
                                                <th style="width: 130px;">Progress</th>
                                                <th style="width: 110px;">Due Date</th>
                                                <th style="width: 120px;">Compilation</th>
                                                <th style="min-width: 180px;">Notes</th>
                                                <th style="width: 100px;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="taskTableBody"></tbody>
                                    </table>
                                </div>
                                <p id="taskListEmpty" class="text-muted mb-0 {{ empty($tasksForJson) ? '' : 'd-none' }}">No tasks yet. Click <strong>Add task</strong> to get started.</p>
                                <div id="taskRowsBody" class="d-none" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('action-logs.index') }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                <i class="ti ti-send me-1"></i> Submit
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script type="application/json" id="actionLogUserOptionsSource">
        @json(collect($teamUsers ?? collect())->map(fn ($teamUser) => ['id' => $teamUser->id, 'name' => $teamUser->name])->values())
    </script>

    @include('action-logs.partials.task-modal', ['modalTitle' => 'Add task'])
    @include('action-logs.partials.task-editor-styles')

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof window.ActionLogTaskEditor === 'undefined') {
                    return;
                }
                window.ActionLogTaskEditor.init({
                    formId: 'createActionLogForm',
                    saveUrl: @json(route('action-logs.store')),
                    usePutMethod: false,
                    periodId: @json($effectivePeriodId),
                    csrfToken: @json(csrf_token()),
                    documentState: 'draft',
                    isCreateMode: true,
                });
            });
        </script>
    @endpush
</x-app-layout>
