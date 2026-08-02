<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Action Logs</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item" aria-current="page">Action Logs</li>
                            </ul>
                      </div>

                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('action-logs.board') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ti ti-layout-kanban me-1"></i> Board
                            </a>
                            @if(($canManageActionLogs ?? false) || auth()->user()->hasPermissionTo('create action logs'))
                                <a href="{{ route('action-logs.create') }}" class="btn btn-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> Open Current Week Log
                                </a>
                            @endif
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

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="ti ti-info-circle me-2"></i>{{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $allCollection = $documents->getCollection();
        $draftCount = $allCollection->where('state', 'draft')->count();
        $submittedCount = $allCollection->where('state', 'submitted')->count();
        $taskCounts = $allCollection->sum(function ($doc) {
            $content = is_array($doc->content) ? $doc->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
            return count($tasks);
        });
    @endphp

    <div class="row mb-3">
        <div class="col-md-4 mb-2">
            <div class="card shadow-none border h-100">
                <div class="card-body py-3">
                    <span class="text-muted d-block">Logs On This Page</span>
                    <h4 class="mb-0">{{ $allCollection->count() }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card shadow-none border h-100">
                <div class="card-body py-3">
                    <span class="text-muted d-block">
                        @if($canManageActionLogs ?? false)
                            Draft / Submitted
                        @else
                            Submitted Logs
                        @endif
                    </span>
                    <h4 class="mb-0">
                        @if($canManageActionLogs ?? false)
                            {{ $draftCount }} / {{ $submittedCount }}
                        @else
                            {{ $submittedCount }}
                        @endif
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-2">
            <div class="card shadow-none border h-100">
                <div class="card-body py-3">
                    <span class="text-muted d-block">Total Tasks (Visible Logs)</span>
                    <h4 class="mb-0">{{ $taskCounts }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row my-2">
        <div class="col-md-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0"><i class="ti ti-list-details me-2"></i>Weekly Action Log Register</h5>
                        </div>
                        <div class="col-md-6 text-right">
                            @if($canManageActionLogs ?? false)
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="{{ route('action-logs.index') }}"
                                       class="btn {{ !request('state') ? 'btn-primary' : 'btn-light' }}">
                                        All
                                    </a>
                                    <a href="{{ route('action-logs.index', ['state' => 'draft']) }}"
                                       class="btn {{ request('state') == 'draft' ? 'btn-primary' : 'btn-light' }}">
                                        Drafts
                                    </a>
                                    <a href="{{ route('action-logs.index', ['state' => 'submitted']) }}"
                                       class="btn {{ request('state') == 'submitted' ? 'btn-primary' : 'btn-light' }}">
                                        Submitted
                                    </a>
                                </div>
                            @else
                                <span class="badge bg-light-success border border-success">Submitted action logs only</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($documents->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered table-striped align-middle">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No.</th>
                                        <th>Action Log</th>
                                        <th>Work Period</th>
                                        <th style="width: 120px;">Tasks</th>
                                        <th style="width: 140px;">Progress</th>
                                        <th style="width: 120px;">State</th>
                                        <th style="width: 140px;">Updated</th>
                                        <th class="text-center" style="width: 170px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($documents as $document)
                                        @php
                                            $content = is_array($document->content) ? $document->content : [];
                                            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
                                            $taskTotal = count($tasks);
                                            $doneTasks = collect($tasks)->filter(fn($row) => is_array($row) && (($row['progress'] ?? '') === 'Done'))->count();
                                            $cancelledTasks = collect($tasks)->filter(fn($row) => is_array($row) && (($row['progress'] ?? '') === 'Cancelled'))->count();
                                            $activeTasks = max(0, $taskTotal - $doneTasks - $cancelledTasks);
                                        @endphp
                                        <tr>
                                            <td class="text-center">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                                            <td>
                                                <strong>{{ $document->display_title }}</strong>
                                                <div><small class="text-muted">Created by {{ $document->user->name ?? 'N/A' }}</small></div>
                                            </td>
                                            <td>
                                                @if($document->period)
                                                    <small class="text-muted">
                                                        Week {{ $document->period->week_number }} of {{ $document->period->year }}<br>
                                                        <span class="text-muted">
                                                            {{ \Carbon\Carbon::parse($document->period->week_start_date)->format('M d') }} - 
                                                            {{ \Carbon\Carbon::parse($document->period->week_end_date)->format('M d, Y') }}
                                                        </span>
                                                    </small>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-light-light border border-light text-dark">{{ $taskTotal }} Task{{ $taskTotal === 1 ? '' : 's' }}</span>
                                            </td>
                                            <td>
                                                @if($taskTotal > 0)
                                                    <span class="badge bg-light-success border border-success">{{ $doneTasks }} Done</span>
                                                    <span class="badge bg-light-secondary border border-secondary">{{ $activeTasks }} Active</span>
                                                    @if($cancelledTasks > 0)
                                                        <span class="badge bg-light-danger border border-danger">{{ $cancelledTasks }} Cancelled</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted">No tasks</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($document->state === 'draft')
                                                    <span class="badge bg-light-warning border border-warning">Draft
                                                    </span>
                                                @else
                                                    <span class="badge bg-light-success border border-success">Submitted
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $document->updated_at->format('M d, Y') }}<br>
                                                    <span>{{ $document->updated_at->diffForHumans() }}</span>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <x-table-action-dropdown>
    <a class="dropdown-item" href="{{ route('action-logs.show', $document->id) }}">
    <i class="ti ti-eye me-2"></i> View
    </a>
    @can('manageActionLogs', $document)
        @if($document->period && $document->period->isOpen())
    <a class="dropdown-item" href="{{ route('action-logs.edit', $document->id) }}">
    <i class="ti ti-edit me-2"></i> Edit
    </a>
        @endif
    @endcan
</x-table-action-dropdown>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3">
                            {{ $documents->links('pagination::bootstrap-5') }}
                        </div>
                   
                    @else
                        <div class="text-center py-5">
                            <i class="ti ti-clipboard fs-1 text-muted"></i>
                            <p class="text-muted mb-3 mt-3">No action logs found.</p>
                            <a href="{{ route('action-logs.create') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-plus-circle me-1"></i> Open Current Week Log
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

