<div class="dash-panel">
    <div class="dash-panel__header">
        <h6 class="dash-panel__title">
            <i class="ti ti-bolt"></i>My Action Tasks
            @if($activeActionTaskCount > 0)
                <span class="badge bg-light-primary border border-primary ms-1">{{ $activeActionTaskCount }}</span>
            @endif
        </h6>
        <a href="{{ route('action-logs.index') }}" class="dash-panel__meta text-decoration-none" data-feature="feature.dashboard.action_tasks">View all</a>
    </div>
    <div class="dash-panel__body">
        @if($activeActionTaskCount > 0)
            <p class="text-muted small mb-2">
                Tasks assigned to you that still need an update.
            </p>
            <div class="list-group list-group-flush dash-task-list">
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
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="small text-dark text-break">{{ $task['task'] ?? 'Task' }}</div>
                                <div class="mt-1">
                                    <span class="badge {{ $badgeClass }}">{{ $tProgress }}</span>
                                    @if(!empty($task['period_label']))
                                        <span class="badge bg-light-light border border-light text-dark">{{ $task['period_label'] }}</span>
                                    @endif
                                    @if(!empty($task['needs_update']))
                                        <span class="badge bg-light-warning border border-warning text-dark">Carried forward</span>
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
                                        class="btn btn-sm btn-primary js-quick-update-task"
                                        data-feature="feature.dashboard.action_tasks"
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
            <div class="dash-panel__empty py-3">
                <i class="ti ti-check text-success"></i>
                <p>No action tasks need your attention right now.</p>
            </div>
        @endif
    </div>
</div>
