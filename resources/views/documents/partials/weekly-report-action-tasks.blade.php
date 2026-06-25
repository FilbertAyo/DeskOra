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
                <i class="ti ti-bolt me-2"></i>Action Tasks (assigned to you)
                @if($pendingActionTaskCount > 0)
                    <span class="badge bg-light-warning border border-warning text-dark ms-1">{{ $pendingActionTaskCount }} pending</span>
                @endif
            </h6>
        </div>
        <div class="card-body">

            @if($pendingActionTaskCount > 0)
                <div class="alert alert-warning py-2 mb-3" role="alert">
                    <i class="ti ti-alert-triangle me-1"></i>
                    {{ $pendingActionTaskCount === 1 ? '1 action task still needs' : $pendingActionTaskCount . ' action tasks still need' }}
                    an update before you can submit this report.
                </div>
            @endif

            @if($activeActionTasks->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 50px;" class="text-center">No.</th>
                                <th>Task</th>
                                <th style="width: 140px;">Week</th>
                                <th style="width: 120px;">Progress</th>
                                <th>Notes</th>
                                <th style="width: 110px;" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeActionTasks as $index => $task)
                                @php
                                    $tProgress = trim((string) ($task['latest_progress'] ?? 'Not Started'));
                                    $badgeClass = match ($tProgress) {
                                        'Done' => 'bg-light-success border border-success',
                                        'Cancelled' => 'bg-light-danger border border-danger',
                                        'In Progress' => 'bg-light-info border border-info',
                                        default => 'bg-light-secondary border border-secondary',
                                    };
                                    $notesText = trim((string) ($task['latest_notes'] ?? ''));
                                    if (! empty($task['needs_update']) && ! empty($task['carried_from_period_label'])) {
                                        $carryNote = 'From '.$task['carried_from_period_label'].' — no update this week yet.';
                                        $notesText = $notesText !== '' ? ($notesText.' '.$carryNote) : $carryNote;
                                    }
                                @endphp
                                <tr @class(['table-warning' => ! empty($task['needs_update'])])>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-break">{{ $task['task'] ?? 'Task' }}</td>
                                    <td>{{ $task['period_label'] ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $badgeClass }}">{{ $tProgress }}</span>
                                    </td>
                                    <td class="text-break">{{ $notesText !== '' ? $notesText : '-' }}</td>
                                    <td class="text-center">
                                        @if(!empty($task['editable']))
                                            <button type="button"
                                                class="btn btn-sm btn-primary js-weekly-report-quick-update-task @if(!empty($task['needs_update'])) js-pending-action-task @endif"
                                                data-task="{{ $task['task'] ?? 'Task' }}"
                                                data-task-uid="{{ $task['task_uid'] }}"
                                                data-document-id="{{ $task['document_id'] }}"
                                                data-progress="{{ $tProgress }}"
                                                data-notes="{{ $task['latest_notes'] ?? '' }}"
                                                data-period="{{ $task['period_label'] ?? '' }}">
                                                Update
                                            </button>
                                        @elseif(!empty($task['document_id']))
                                            <a href="{{ route('action-logs.show', $task['document_id']) }}" class="btn btn-sm btn-outline-secondary" title="View action log">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
