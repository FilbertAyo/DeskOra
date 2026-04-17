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


    <div class="row">
        <div class="col-12 mb-3">
            <div class="row g-3">
                <!-- Compact Info Card -->
                <div class="col-12 col-lg-10">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2">
                            <h6 class="mb-0">Action Log Information</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-3">
                           
                                <div class="col-12 col-md-2">
                                    <label class="text-muted small mb-1 d-block">State</label>
                                    @if($document->state === 'draft')
                                        <span class="badge bg-warning">
                                            Draft
                                        </span>
                                    @else
                                        <span class="badge bg-success">
                                            Submitted
                                        </span>
                                    @endif
                                </div>

                                <div class="col-12 col-md-2">
                                    <label class="text-muted small mb-1 d-block">Created By</label>
                                    <strong class="text-muted small">
                                     {{ $document->user->name }}
                                    </strong>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="text-muted small mb-1 d-block">Created At</label>
                                    <strong class="text-muted small">
                                        {{ $document->created_at->format('M d, Y h:i A') }}
                                    </strong>
                                </div>

                                <div class="col-12 col-md-2">
                                    <label class="text-muted small mb-1 d-block">Last Updated</label>
                                    <strong class="text-muted small">
                                        {{ $document->updated_at->format('M d, Y h:i A') }}
                                    </strong>
                                </div>

                                @if($document->period)
                                    <div class="col-12 col-md-2">
                                        <label class="text-muted small mb-1 d-block">Work Period</label>
                                        <strong class="text-muted small">
                                            Week {{ $document->period->week_number }} of {{ $document->period->year }}
                                        </strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compact Actions Card -->
                <div class="col-12 col-lg-2">
                    <div class="card shadow-none border h-100">
                        <div class="card-header bg-white border-bottom py-2">
                            <h6 class="mb-0"><i class="ti ti-activity me-2"></i>Actions</h6>
                        </div>
                        <div class="card-body py-3">
                            <div class="row g-2">
                                @can('update', $document)
                                    <div class="col-12 col-md-6">
                                        <a href="{{ route('action-logs.edit', $document->id) }}" class="btn btn-primary btn-sm w-100">
                                            <i class="ti ti-edit me-1"></i> Edit 
                                        </a>
                                    </div>
                                @endcan

                                @if($document->canBeDeleted())
                                    <div class="col-12 col-md-6">
                                        <form action="{{ route('action-logs.destroy', $document->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this action log? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                                <i class="ti ti-trash-2 me-1"></i> Delete 
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Log Content -->
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        {{ $document->display_title }}
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $content = is_array($document->content) ? $document->content : [];
                        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
                        $responsibleIds = collect($tasks)
                            ->filter(fn($row) => is_array($row) && is_array($row['responsible_user_ids'] ?? null))
                            ->flatMap(fn($row) => $row['responsible_user_ids'])
                            ->filter()
                            ->map(fn($id) => (string) $id)
                            ->unique()
                            ->values()
                            ->all();
                        $historyUpdaterIds = collect($tasks)
                            ->filter(fn($row) => is_array($row) && is_array($row['history'] ?? null))
                            ->flatMap(fn($row) => $row['history'])
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
                                        <th style="width: 70px;">No</th>
                                        <th style="min-width: 300px;">Task</th>
                                        <th style="width: 250px;">Responsible Person</th>
                                        <th style="width: 160px;">Progress</th>
                                        <th style="width: 140px;">Due Date</th>
                                        <th>Compilation Date</th>
                                        <th style="width: 300px;">Notes/Progress</th>
                                        <th style="width: 210px;">Cancel Reason</th>
                                        <th>Timeline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tasks as $row)
                                        @php
                                            $row = is_array($row) ? $row : [];
                                            $ids = is_array($row['responsible_user_ids'] ?? null) ? array_map('strval', $row['responsible_user_ids']) : [];
                                            $names = collect($ids)->map(fn($id) => $responsibleNameMap[$id] ?? null)->filter()->values()->all();
                                            $history = is_array($row['history'] ?? null) ? array_values(array_filter($row['history'], fn($entry) => is_array($entry))) : [];
                                            $timelineModalId = 'taskTimelineModal' . $loop->index;
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ $row['task_no'] ?? $loop->iteration }}</td>
                                            <td class="text-wrap small">{{ $row['task'] ?? '' }}</td>
                                            <td class="text-muted small">{{ !empty($names) ? implode(', ', $names) : '-' }}</td>
                                            <td>
                                                @php $progress = $row['progress'] ?? 'Not Started'; @endphp
                                                @if($progress === 'Done')
                                                    <span class="badge bg-success">{{ $progress }}</span>
                                                @elseif($progress === 'Cancelled')
                                                    <span class="badge bg-danger">{{ $progress }}</span>
                                                @elseif($progress === 'In Progress')
                                                    <span class="badge bg-info">{{ $progress }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ $progress }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $row['due_date'] ?? '-' }}</td>
                                            <td>{{ $row['compilation_date'] ?? '-' }}</td>
                                            <td class="text-muted small">{{ $row['notes_progress'] ?? '' }}</td>
                                            <td class="text-muted small">{{ $row['status_reason'] ?? '-' }}</td>
                                            <td>
                                                @if(!empty($history))
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-primary"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#{{ $timelineModalId }}"
                                                        aria-expanded="false"
                                                        aria-controls="{{ $timelineModalId }}">
                                                        View Timeline
                                                    </button>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>

                                        @if(!empty($history))
                                            <tr class="collapse bg-light" id="{{ $timelineModalId }}">
                                                <td colspan="9">
                                                    <h6 class="mb-2">Task Timeline</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered align-middle mb-0">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th>Week Period</th>
                                                                    <th>Changed By</th>
                                                                    <th>Status Change</th>
                                                                    <th>Notes/Progress</th>
                                                                    <th>Reason</th>
                                                                    <th>Changed At</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach(collect($history)->reverse() as $entry)
                                                                    @php
                                                                        $updaterId = (string) ($entry['updated_by'] ?? '');
                                                                        $updaterName = $responsibleNameMap[$updaterId] ?? 'Unknown user';
                                                                        $fromProgress = $entry['from_progress'] ?? null;
                                                                        $toProgress = $entry['to_progress'] ?? '-';
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $entry['period_label'] ?? '-' }}</td>
                                                                        <td>{{ $updaterName }}</td>
                                                                        <td>
                                                                            @if($fromProgress)
                                                                                {{ $fromProgress }} -> {{ $toProgress }}
                                                                            @else
                                                                                Initial: {{ $toProgress }}
                                                                            @endif
                                                                        </td>
                                                                        <td>{{ $entry['notes_progress'] ?? '-' }}</td>
                                                                        <td>{{ $entry['status_reason'] ?? '-' }}</td>
                                                                        <td>{{ $entry['changed_at'] ?? '-' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
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
</x-app-layout>

