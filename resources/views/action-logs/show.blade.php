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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ti ti-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

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
                                <span class="badge bg-warning"><i class="ti ti-edit me-1"></i>Draft</span>
                            @else
                                <span class="badge bg-success"><i class="ti ti-check-circle me-1"></i>Submitted</span>
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
                                    <span class="badge bg-light text-dark border">{{ $taskTotal }} Total</span>
                                    <span class="badge bg-success">{{ $doneTasks }} Done</span>
                                    <span class="badge bg-info">{{ $inProgressTasks }} In Progress</span>
                                    <span class="badge bg-secondary">{{ $notStartedTasks }} Not Started</span>
                                    @if($cancelledTasks > 0)
                                        <span class="badge bg-danger">{{ $cancelledTasks }} Cancelled</span>
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
                                @can('update', $document)
                                    <a href="{{ route('action-logs.edit', $document->id) }}" class="btn btn-primary btn-sm">
                                        <i class="ti ti-edit me-1"></i> Update Tasks
                                    </a>
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
                    <small class="text-muted"><i class="ti ti-history me-1"></i>Click <strong>View Timeline</strong> on any task to see its full week-by-week history.</small>
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
                                        <th style="min-width: 240px;">Latest Notes</th>
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
                                            $timelineId = 'taskTimeline' . $loop->index;
                                            $displayNotes = array_key_exists('display_notes_progress', $row) ? (string) $row['display_notes_progress'] : (string) ($row['notes_progress'] ?? '');
                                            $displayProgress = array_key_exists('display_progress', $row) ? $row['display_progress'] : ($row['progress'] ?? 'Not Started');
                                        @endphp
                                        <tr>
                                            <td class="text-center fw-bold">{{ $row['task_no'] ?? $loop->iteration }}</td>
                                            <td class="text-wrap">{{ $row['task'] ?? '' }}</td>
                                            <td class="text-muted small">{{ !empty($names) ? implode(', ', $names) : '-' }}</td>
                                            <td>
                                                @if($displayProgress === null)
                                                    <span class="text-muted">—</span>
                                                @elseif($displayProgress === 'Done')
                                                    <span class="badge bg-success">{{ $displayProgress }}</span>
                                                @elseif($displayProgress === 'Cancelled')
                                                    <span class="badge bg-danger">{{ $displayProgress }}</span>
                                                @elseif($displayProgress === 'In Progress')
                                                    <span class="badge bg-info">{{ $displayProgress }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $displayProgress }}</span>
                                                @endif
                                            </td>
                                            <td class="small">{{ $row['due_date'] ?? '-' }}</td>
                                            <td class="small">{{ $row['compilation_date'] ?? '-' }}</td>
                                            <td class="text-muted small">{{ $displayNotes !== '' ? $displayNotes : '—' }}</td>
                                            <td>
                                                @if(!empty($realHistory))
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#{{ $timelineId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $timelineId }}">
                                                        <i class="ti ti-timeline-event me-1"></i>View Timeline
                                                        <span class="badge bg-primary ms-1">{{ $realHistory->count() }}</span>
                                                    </button>
                                                @else
                                                    <span class="text-muted small">No updates yet</span>
                                                @endif
                                            </td>
                                        </tr>

                                        @if(!empty($realHistory))
                                            <tr class="collapse bg-light" id="{{ $timelineId }}">
                                                <td colspan="8">
                                                    <div class="p-2">
                                                        <h6 class="mb-3 text-primary">
                                                            <i class="ti ti-history me-1"></i>Full Timeline &mdash; {{ $row['task'] ?? 'Task' }}
                                                        </h6>
                                                        <ul class="al-timeline">
                                                            @foreach($realHistory as $entry)
                                                                @php
                                                                    $updaterId = (string) ($entry['updated_by'] ?? '');
                                                                    $updaterName = $updaterId !== '' ? ($responsibleNameMap[$updaterId] ?? 'Unknown user') : '—';
                                                                    $fromProgress = $entry['from_progress'] ?? null;
                                                                    $toProgress = $entry['to_progress'] ?? '-';
                                                                    $dotClass = match($toProgress) {
                                                                        'Done' => 'al-dot-success',
                                                                        'Cancelled' => 'al-dot-danger',
                                                                        'In Progress' => 'al-dot-info',
                                                                        default => 'al-dot-secondary',
                                                                    };
                                                                @endphp
                                                                <li class="al-timeline-item">
                                                                    <span class="al-dot {{ $dotClass }}"></span>
                                                                    <div class="al-timeline-content">
                                                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
                                                                            <strong class="text-dark">{{ $entry['period_label'] ?? '-' }}</strong>
                                                                            <small class="text-muted">{{ $entry['changed_at'] ?? '' }}</small>
                                                                        </div>
                                                                        <div class="mb-1">
                                                                            @if($fromProgress)
                                                                                <span class="badge bg-light text-dark border">{{ $fromProgress }}</span>
                                                                                <i class="ti ti-arrow-right text-muted mx-1"></i>
                                                                            @endif
                                                                            <span class="badge {{ $dotClass === 'al-dot-success' ? 'bg-success' : ($dotClass === 'al-dot-danger' ? 'bg-danger' : ($dotClass === 'al-dot-info' ? 'bg-info' : 'bg-secondary')) }}">{{ $toProgress }}</span>
                                                                        </div>
                                                                        @if(!empty($entry['notes_progress']))
                                                                            <div class="small text-dark"><i class="ti ti-note me-1 text-muted"></i>{{ $entry['notes_progress'] }}</div>
                                                                        @endif
                                                                        @if(!empty($entry['status_reason']))
                                                                            <div class="small text-danger"><i class="ti ti-alert-triangle me-1"></i>{{ $entry['status_reason'] }}</div>
                                                                        @endif
                                                                        <div class="small text-muted mt-1"><i class="ti ti-user me-1"></i>{{ $updaterName }}</div>
                                                                    </div>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
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
</x-app-layout>
