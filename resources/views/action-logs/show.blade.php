<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Action Log Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('action-logs.index') }}">Action Logs</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    @php
        $taskTotal = count($tasks);
        $doneTasks = collect($tasks)->filter(fn($r) => is_array($r) && (($r['display_progress'] ?? $r['progress'] ?? '') === 'Done'))->count();
        $cancelledTasks = collect($tasks)->filter(fn($r) => is_array($r) && (($r['display_progress'] ?? $r['progress'] ?? '') === 'Cancelled'))->count();
        $inProgressTasks = collect($tasks)->filter(fn($r) => is_array($r) && (($r['display_progress'] ?? $r['progress'] ?? '') === 'In Progress'))->count();
        $notStartedTasks = max(0, $taskTotal - $doneTasks - $cancelledTasks - $inProgressTasks);
        $completionPercent = $taskTotal > 0 ? round((($doneTasks + $cancelledTasks) / $taskTotal) * 100) : 0;
    @endphp

    <div class="row">
        <div class="col-12 mb-3">
            <div class="row g-3">
                <div class="col-12 col-lg-9">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="ti ti-info-circle me-2"></i>Action Log Information</h6>
                            @if($document->state === 'draft')
                                <span class="badge bg-light-warning border border-warning">Draft</span>
                            @else
                                <span class="badge bg-light-success border border-success">Submitted</span>
                            @endif
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="text-muted small mb-1 d-block">Work Period</label>
                                    <strong class="small">
                                        @if($document->period)
                                            Week {{ $document->period->week_number }} of {{ $document->period->year }}
                                        @else
                                            N/A
                                        @endif
                                    </strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="text-muted small mb-1 d-block">Created By</label>
                                    <strong class="small">{{ $document->user->name }}</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="text-muted small mb-1 d-block">Created At</label>
                                    <strong class="small">{{ $document->created_at->format('M d, Y h:i A') }}</strong>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="text-muted small mb-1 d-block">Last Updated</label>
                                    <strong class="small">{{ $document->updated_at->format('M d, Y h:i A') }}</strong>
                                </div>
                            </div>

                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Overall completion</span>
                                    <span class="small fw-bold">{{ $completionPercent }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $completionPercent }}%"
                                        aria-valuenow="{{ $completionPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <span class="badge bg-light-light border border-light text-dark">{{ $taskTotal }} Total</span>
                                    <span class="badge bg-light-success border border-success">{{ $doneTasks }} Done</span>
                                    <span class="badge bg-light-info border border-info">{{ $inProgressTasks }} In Progress</span>
                                    <span class="badge bg-light-secondary border border-secondary">{{ $notStartedTasks }} Not Started</span>
                                    @if($cancelledTasks > 0)
                                        <span class="badge bg-light-danger border border-danger">{{ $cancelledTasks }} Cancelled</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-3">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2">
                            <h6 class="mb-0"><i class="ti ti-activity me-2"></i>Actions</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="d-grid gap-2">
                                @can('manageActionLogs', $document)
                                    @if($document->period && $document->period->isOpen())
                                    <a href="{{ route('action-logs.edit', $document->id) }}" class="btn btn-primary btn-sm">
                                        <i class="ti ti-edit me-1"></i> Update Tasks
                                    </a>
                                    @endif
                                @endcan
                                <a href="{{ route('action-logs.download', $document->id) }}" class="btn btn-outline-danger btn-sm">
                                    <i class="ti ti-download me-1"></i> Download PDF
                                </a>
                                @can('manageActionLogs', $document)
                                    @if($document->canBeDeleted())
                                        <form action="{{ route('action-logs.destroy', $document->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this action log? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                <i class="ti ti-trash me-1"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Log Content -->
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">{{ $document->display_title }}</h5>
                    <small class="text-muted"><i class="ti ti-history me-1"></i>Click <strong>Progress</strong> on any task to see its full week-by-week history.</small>
                </div>
                <div class="card-body">
                    @php
                        $responsibleIds = collect($tasks)
                            ->filter(fn($row) => is_array($row) && is_array($row['responsible_user_ids'] ?? null))
                            ->flatMap(fn($row) => $row['responsible_user_ids'])
                            ->filter()
                            ->map(fn($id) => (string) $id)
                            ->unique()
                            ->values()
                            ->all();
                        $historyUpdaterIds = collect($tasks)
                            ->filter(fn($row) => is_array($row) && is_array($row['display_history'] ?? null))
                            ->flatMap(fn($row) => $row['display_history'])
                            ->filter(fn($entry) => is_array($entry) && !empty($entry['updated_by']))
                            ->map(fn($entry) => (string) $entry['updated_by'])
                            ->unique()
                            ->values()
                            ->all();
                        $allUserIds = collect(array_merge($responsibleIds, $historyUpdaterIds))->unique()->values()->all();
                        $responsibleNameMap = \App\Models\User::whereIn('id', $allUserIds)->pluck('name', 'id')->toArray();
                    @endphp

                    @if(!empty($tasks))
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 60px;">No</th>
                                        <th style="min-width: 280px;">Task</th>
                                        <th style="width: 200px;">Responsible</th>
                                        <th style="width: 150px;">Progress</th>
                                        <th style="width: 120px;">Due Date</th>
                                        <th style="width: 130px;">Compilation</th>
                                        <th style="min-width: 240px;">Notes/Progress</th>
                                        <th style="width: 140px;">Timeline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tasks as $row)
                                        @php
                                            $row = is_array($row) ? $row : [];
                                            $ids = is_array($row['responsible_user_ids'] ?? null) ? array_map('strval', $row['responsible_user_ids']) : [];
                                            $names = collect($ids)->map(fn($id) => $responsibleNameMap[$id] ?? null)->filter()->values()->all();
                                            $history = is_array($row['display_history'] ?? null) ? array_values(array_filter($row['display_history'], fn($entry) => is_array($entry))) : [];
                                            $realHistory = collect($history)->reject(fn($e) => (bool) ($e['is_empty'] ?? false))->values();
                                            $displayNotes = array_key_exists('display_notes_progress', $row) ? (string) $row['display_notes_progress'] : (string) ($row['notes_progress'] ?? '');
                                            $displayProgress = array_key_exists('display_progress', $row) ? $row['display_progress'] : ($row['progress'] ?? 'Not Started');
                                            $historyForOffcanvas = collect($history)->map(function ($entry) use ($responsibleNameMap) {
                                                if ((bool) ($entry['is_empty'] ?? false)) {
                                                    return [
                                                        'period_label' => $entry['period_label'] ?? '-',
                                                        'is_empty' => true,
                                                    ];
                                                }

                                                $updaterId = (string) ($entry['updated_by'] ?? '');
                                                $toProgress = $entry['to_progress'] ?? '-';

                                                return [
                                                    'period_label' => $entry['period_label'] ?? '-',
                                                    'changed_at' => $entry['changed_at'] ?? '',
                                                    'from_progress' => $entry['from_progress'] ?? null,
                                                    'to_progress' => $toProgress,
                                                    'notes_progress' => $entry['notes_progress'] ?? '',
                                                    'status_reason' => $entry['status_reason'] ?? '',
                                                    'updater_name' => $updaterId !== '' ? ($responsibleNameMap[$updaterId] ?? 'Unknown user') : '—',
                                                    'dot_class' => match ($toProgress) {
                                                        'Done' => 'al-dot-success',
                                                        'Cancelled' => 'al-dot-danger',
                                                        'In Progress' => 'al-dot-info',
                                                        default => 'al-dot-secondary',
                                                    },
                                                    'is_empty' => false,
                                                ];
                                            })->values()->all();
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-bold">{{ $row['task_no'] ?? $loop->iteration }}</td>
                                            <td class="text-wrap">{{ $row['task'] ?? '' }}</td>
                                            <td class="text-muted small">
                                                @if(!empty($names))
                                                    {{ implode(', ', $names) }}
                                                @else
                                                    <span class="badge bg-light-warning border border-warning text-dark">Needs assignment</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($displayProgress === null)
                                                    <span class="text-muted">—</span>
                                                @elseif($displayProgress === 'Done')
                                                    <span class="badge bg-light-success border border-success">{{ $displayProgress }}</span>
                                                @elseif($displayProgress === 'Cancelled')
                                                    <span class="badge bg-light-danger border border-danger">{{ $displayProgress }}</span>
                                                @elseif($displayProgress === 'In Progress')
                                                    <span class="badge bg-light-info border border-info">{{ $displayProgress }}</span>
                                                @else
                                                    <span class="badge bg-light-secondary border border-secondary">{{ $displayProgress }}</span>
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if(!empty($row['due_date']))
                                                    {{ \Carbon\Carbon::parse($row['due_date'])->format('M d, Y') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="small">
                                                @if(!empty($row['compilation_date']))
                                                    {{ \Carbon\Carbon::parse($row['compilation_date'])->format('M d, Y') }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-muted small">{{ $displayNotes !== '' ? $displayNotes : '—' }}</td>
                                            <td>
                                                @if(!empty($history))
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary js-view-task-timeline"
                                                        data-bs-toggle="offcanvas"
                                                        data-bs-target="#actionLogTaskTimelineOffcanvas"
                                                        data-task-title="{{ e($row['task'] ?? 'Task') }}"
                                                        data-history='@json($historyForOffcanvas)'>
                                                        <i class="ti ti-timeline-event me-1"></i>Progress
                                                        <span class="badge bg-light-primary border border-primary ms-1">{{ count($history) }}</span>
                                                    </button>
                                                @else
                                                    <span class="text-muted small">No updates yet</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-2"></i>
                            No structured action log tasks found for this document.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="actionLogTaskTimelineOffcanvas" aria-labelledby="actionLogTaskTimelineOffcanvasLabel" style="width: min(480px, 100vw);">
        <div class="offcanvas-header border-bottom">
            <div>
                <h5 class="offcanvas-title mb-1" id="actionLogTaskTimelineOffcanvasLabel">Task Progress</h5>
                <p class="text-muted small mb-0" id="actionLogTaskTimelineSubtitle">Week-by-week history</p>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body" id="actionLogTaskTimelineBody">
            <p class="text-muted small mb-0">Select a task to view its progress history.</p>
        </div>
    </div>

    <style>
        .al-timeline {
            list-style: none;
            margin: 0;
            padding: 0 0 0 8px;
            position: relative;
        }
        .al-timeline:before {
            content: '';
            position: absolute;
            left: 6px;
            top: 4px;
            bottom: 4px;
            width: 2px;
            background: #e3e6ef;
        }
        .al-timeline-item {
            position: relative;
            padding: 0 0 16px 28px;
        }
        .al-timeline-item:last-child {
            padding-bottom: 0;
        }
        .al-dot {
            position: absolute;
            left: 0;
            top: 2px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #e3e6ef;
        }
        .al-dot-success { background: #2ca87f; }
        .al-dot-danger { background: #dc3545; }
        .al-dot-info { background: #3ec9d6; }
        .al-dot-secondary { background: #748892; }
        .al-timeline-content {
            background: #fff;
            border: 1px solid #e3e6ef;
            border-radius: 6px;
            padding: 8px 12px;
        }
    </style>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const offcanvasEl = document.getElementById('actionLogTaskTimelineOffcanvas');
                const titleEl = document.getElementById('actionLogTaskTimelineOffcanvasLabel');
                const subtitleEl = document.getElementById('actionLogTaskTimelineSubtitle');
                const bodyEl = document.getElementById('actionLogTaskTimelineBody');

                if (!offcanvasEl || typeof bootstrap === 'undefined') {
                    return;
                }

                let pendingTimeline = null;

                document.querySelectorAll('.js-view-task-timeline').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        let history = [];
                        const rawHistory = btn.getAttribute('data-history');
                        if (rawHistory) {
                            try {
                                history = JSON.parse(rawHistory);
                            } catch (e) {
                                history = [];
                            }
                        }

                        pendingTimeline = {
                            title: btn.getAttribute('data-task-title') || 'Task',
                            history: Array.isArray(history) ? history : [],
                        };
                    });
                });

                function progressBadgeClass(dotClass) {
                    if (dotClass === 'al-dot-success') return 'bg-light-success border border-success';
                    if (dotClass === 'al-dot-danger') return 'bg-light-danger border border-danger';
                    if (dotClass === 'al-dot-info') return 'bg-light-info border border-info';
                    return 'bg-light-secondary border border-secondary';
                }

                function renderTimeline(history) {
                    if (!Array.isArray(history) || history.length === 0) {
                        bodyEl.innerHTML = '<p class="text-muted small mb-0">No progress updates yet.</p>';
                        return;
                    }

                    const list = document.createElement('ul');
                    list.className = 'al-timeline';

                    history.forEach(function (entry) {
                        const item = document.createElement('li');
                        item.className = 'al-timeline-item';

                        const dot = document.createElement('span');
                        dot.className = 'al-dot ' + (entry.is_empty ? 'al-dot-secondary' : (entry.dot_class || 'al-dot-secondary'));

                        const content = document.createElement('div');
                        content.className = 'al-timeline-content';

                        const header = document.createElement('div');
                        header.className = 'd-flex justify-content-between align-items-start flex-wrap gap-1';
                        header.innerHTML = '<strong class="text-dark"></strong><small class="text-muted"></small>';
                        header.querySelector('strong').textContent = entry.period_label || '-';
                        header.querySelector('small').textContent = entry.is_empty ? '' : (entry.changed_at || '');

                        content.appendChild(header);

                        if (entry.is_empty) {
                            const emptyStatus = document.createElement('div');
                            emptyStatus.className = 'small text-muted fst-italic';
                            emptyStatus.textContent = 'Not updated';
                            content.appendChild(emptyStatus);
                        } else {
                            const progressRow = document.createElement('div');
                            progressRow.className = 'mb-1';
                            if (entry.from_progress) {
                                const fromBadge = document.createElement('span');
                                fromBadge.className = 'badge bg-light-light border border-light text-dark';
                                fromBadge.textContent = entry.from_progress;
                                progressRow.appendChild(fromBadge);
                                progressRow.appendChild(document.createTextNode(' '));
                                const arrow = document.createElement('i');
                                arrow.className = 'ti ti-arrow-right text-muted mx-1';
                                progressRow.appendChild(arrow);
                            }
                            const toBadge = document.createElement('span');
                            toBadge.className = 'badge ' + progressBadgeClass(entry.dot_class);
                            toBadge.textContent = entry.to_progress || '-';
                            progressRow.appendChild(toBadge);

                            content.appendChild(progressRow);

                            if (entry.notes_progress) {
                                const notes = document.createElement('div');
                                notes.className = 'small text-dark';
                                notes.innerHTML = '<i class="ti ti-note me-1 text-muted"></i>';
                                notes.appendChild(document.createTextNode(entry.notes_progress));
                                content.appendChild(notes);
                            }

                            if (entry.status_reason) {
                                const reason = document.createElement('div');
                                reason.className = 'small text-danger';
                                reason.innerHTML = '<i class="ti ti-alert-triangle me-1"></i>';
                                reason.appendChild(document.createTextNode(entry.status_reason));
                                content.appendChild(reason);
                            }

                            const updater = document.createElement('div');
                            updater.className = 'small text-muted mt-1';
                            updater.innerHTML = '<i class="ti ti-user me-1"></i>';
                            updater.appendChild(document.createTextNode(entry.updater_name || '—'));
                            content.appendChild(updater);
                        }

                        item.appendChild(dot);
                        item.appendChild(content);
                        list.appendChild(item);
                    });

                    bodyEl.innerHTML = '';
                    bodyEl.appendChild(list);
                }

                offcanvasEl.addEventListener('show.bs.offcanvas', function () {
                    if (!pendingTimeline) {
                        return;
                    }

                    titleEl.textContent = 'Task Progress';
                    subtitleEl.textContent = pendingTimeline.title;
                    renderTimeline(pendingTimeline.history);
                });
            });
        </script>
    @endpush
</x-app-layout>
