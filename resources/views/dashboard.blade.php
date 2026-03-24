<x-app-layout>
    @php
        $dashboardData = $weeklyDashboardData ?? null;
        $pendingDocs = $dashboardData['pendingDocuments'] ?? collect();
        $allDocs = $dashboardData['allDocuments'] ?? collect();
        $deadlineStatuses = $dashboardData['deadlineStatuses'] ?? ($periodDeadlineStatuses ?? null);
        $availableTypes = $dashboardData['availableTypes'] ?? ($dashboardAvailableTypes ?? []);
        $returnedDocs = collect($pendingDocs)->filter(fn ($doc) => method_exists($doc, 'isReturnedForRevision') && $doc->isReturnedForRevision());
        $plainDraftDocs = collect($pendingDocs)->filter(fn ($doc) => !method_exists($doc, 'isReturnedForRevision') || !$doc->isReturnedForRevision());
        $hasReviewerAccess = auth()->check() && auth()->user()->hasPermissionTo('view all documents');
        $recentFeedbackComments = collect($allDocs)
            ->flatMap(function ($doc) {
                $comments = $doc->comments ?? collect();
                return collect($comments)->map(function ($comment) use ($doc) {
                    return [
                        'document' => $doc,
                        'comment' => $comment,
                    ];
                });
            })
            ->filter(function ($row) {
                $raw = (string) ($row['comment']->comment ?? '');
                return str_starts_with($raw, \App\Models\Document::REVIEW_REJECTION_PREFIX);
            })
            ->sortByDesc(fn ($row) => optional($row['comment'])->created_at)
            ->take(5)
            ->values();

        $activeActionTasks = $actionLogProgress['activeTasks'] ?? [];
        $activeActionTaskCount = is_array($activeActionTasks) ? count($activeActionTasks) : 0;
        $currentActionLogDraftId = $actionLogProgress['currentDraftId'] ?? null;
        $shouldShowActionToast = $activeActionTaskCount > 0;
    @endphp

    @if($shouldShowActionToast)
        <div class="position-fixed top-0 end-0 p-3 action-toast-wrap" style="z-index: 1080">
            <div id="actionLogDashboardToast" class="toast hide border-0 shadow-sm action-toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header py-2">
                    <i class="ti ti-bell-ringing text-primary me-2"></i>
                    <strong class="me-auto">Action Log</strong>
                    <small class="text-muted">{{ $activeActionTaskCount }} open</small>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body py-2">
                    <p class="mb-2 small">
                        {{ $activeActionTaskCount }} task{{ $activeActionTaskCount === 1 ? '' : 's' }} need update.
                    </p>
                    <div class="d-flex justify-content-end">
                        @if($currentActionLogDraftId)
                            <a href="{{ route('action-logs.edit', $currentActionLogDraftId) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-edit me-1"></i>Update
                            </a>
                        @else
                            <a href="{{ route('action-logs.index') }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-eye me-1"></i>View Action Logs
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- [ breadcrumb ] start -->
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Dashboard</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0)">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Dashboard</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Active Users This Month</h6>
                    <h4 class="mb-3">
                        {{ number_format($activeUsers ?? 0) }}
                        <span class="badge bg-light-success border border-success">
                            <i class="ti ti-trending-up"></i> 2.3%
                        </span>
                    </h4>
                    <p class="mb-0 text-muted text-sm">
                        {{ $currentPeriod ? 'Work period is currently set for this week.' : 'No active work period.' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Users</h6>
                    <h4 class="mb-3">
                        {{ number_format($userNo ?? 0) }}
                        <span class="badge bg-light-primary border border-primary">
                            <i class="ti ti-trending-up"></i> 1.1%
                        </span>
                    </h4>
                    <p class="mb-0 text-muted text-sm">All active staff and requesters.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Petty Cash Requests</h6>
                    <h4 class="mb-3">
                        {{ number_format($pettyNo ?? 0) }}
                        <span class="badge bg-light-warning border border-warning">
                            <i class="ti ti-trending-down"></i> 0.8%
                        </span>
                    </h4>
                    <p class="mb-0 text-muted text-sm">Pending and processed cashflow requests.</p>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2 f-w-400 text-muted">Total Paid Amount</h6>
                    <h4 class="mb-3">
                        {{ number_format($paidAmount ?? 0, 2) }}
                        <span class="badge bg-light-danger border border-danger">
                            <i class="ti ti-trending-down"></i> 1.4%
                        </span>
                    </h4>
                    <p class="mb-0 text-muted text-sm">Total amount marked as paid.</p>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-bell me-2"></i>My Notifications</h5>
                    <span class="badge bg-primary">
                        {{ ($returnedDocs->count() ?? 0) + ($plainDraftDocs->count() ?? 0) + $activeActionTaskCount }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-arrow-back-up text-danger me-1"></i>Returned for revision
                                </p>
                                <h4 class="mb-1 text-danger">{{ $returnedDocs->count() }}</h4>
                                <small class="text-muted">Update and re-submit these first.</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-file-edit text-warning me-1"></i>Drafts waiting submission
                                </p>
                                <h4 class="mb-1 text-warning">{{ $plainDraftDocs->count() }}</h4>
                                <small class="text-muted">Ready to review and submit.</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 border rounded h-100">
                                <p class="mb-1 text-muted small">
                                    <i class="ti ti-clipboard-list text-primary me-1"></i>Open action tasks
                                </p>
                                <h4 class="mb-1 text-primary">{{ $activeActionTaskCount }}</h4>
                                <small class="text-muted">Track progress updates for your assigned tasks.</small>
                            </div>
                        </div>
                    </div>

                    @if($deadlineStatuses)
                        <div class="alert alert-light border mb-0">
                            <div class="d-flex flex-wrap gap-3 align-items-center">
                                <span>
                                    <strong>Plan deadline:</strong>
                                    {{ \Carbon\Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y') }}
                                    <span class="badge {{ $deadlineStatuses['plan_badge'] }} ms-1">{{ $deadlineStatuses['plan_text'] }}</span>
                                </span>
                                <span>
                                    <strong>Report deadline:</strong>
                                    {{ \Carbon\Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y') }}
                                    <span class="badge {{ $deadlineStatuses['report_badge'] }} ms-1">{{ $deadlineStatuses['report_text'] }}</span>
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-bolt me-2"></i>Quick Shortcuts</h5>
                    <small class="text-muted">Personalized actions</small>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('documents.index') }}" class="btn btn-light text-start">
                            <i class="ti ti-folders me-2 text-primary"></i>My Documents Hub
                        </a>
                        @if(isset($availableTypes['weekly_plan']))
                            <a href="{{ route('documents.create', ['type' => 'weekly_plan']) }}" class="btn btn-light text-start">
                                <i class="ti ti-calendar-plus me-2 text-info"></i>Create Weekly Plan
                            </a>
                        @endif
                        @if(isset($availableTypes['weekly_report']))
                            <a href="{{ route('documents.create', ['type' => 'weekly_report']) }}" class="btn btn-light text-start">
                                <i class="ti ti-file-analytics me-2 text-success"></i>Create Weekly Report
                            </a>
                        @endif
                        <a href="{{ route('action-logs.index') }}" class="btn btn-light text-start">
                            <i class="ti ti-list-details me-2 text-warning"></i>Action Logs
                        </a>
                        <a href="{{ route('profile.edit') }}" class="btn btn-light text-start">
                            <i class="ti ti-user-cog me-2 text-secondary"></i>My Profile Settings
                        </a>
                        @if($hasReviewerAccess)
                            <a href="{{ route('documents.reviewer-dashboard') }}" class="btn btn-light text-start">
                                <i class="ti ti-eye-check me-2 text-danger"></i>Reviewer Dashboard
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7 col-12">
            <div class="card shadow-none border h-100">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-checklist me-2"></i>My Focus Tasks</h5>
                    <small class="text-muted">What to do next</small>
                </div>
                <div class="card-body">
                    @if($returnedDocs->count() > 0)
                        <div class="mb-3">
                            <h6 class="text-danger mb-2"><i class="ti ti-arrow-back-up me-1"></i>Needs Immediate Revision</h6>
                            @foreach($returnedDocs->take(3) as $doc)
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <div>
                                        <strong>{{ $doc->display_title }}</strong>
                                        <div class="small text-muted">
                                            {{ $doc->type_name }} • Updated {{ $doc->updated_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($doc->id)) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                        <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($doc->id)) }}" class="btn btn-sm btn-danger">Revise</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($plainDraftDocs->count() > 0)
                        <div class="mb-3">
                            <h6 class="text-warning mb-2"><i class="ti ti-file-edit me-1"></i>Drafts To Finalize</h6>
                            @foreach($plainDraftDocs->take(3) as $doc)
                                <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                    <div>
                                        <strong>{{ $doc->display_title }}</strong>
                                        <div class="small text-muted">{{ $doc->type_name }} • Created {{ $doc->created_at?->format('M d, Y') }}</div>
                                    </div>
                                    <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($doc->id)) }}" class="btn btn-sm btn-warning text-dark">Continue</a>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($recentFeedbackComments->count() > 0)
                        <div>
                            <h6 class="text-primary mb-2"><i class="ti ti-message-circle me-1"></i>Latest Reviewer Feedback</h6>
                            @foreach($recentFeedbackComments as $feedback)
                                @php
                                    $rawComment = (string) ($feedback['comment']->comment ?? '');
                                    $cleanReason = trim(substr($rawComment, strlen(\App\Models\Document::REVIEW_REJECTION_PREFIX)));
                                @endphp
                                <div class="border-start border-3 border-primary ps-3 mb-2">
                                    <div class="small fw-semibold">{{ $feedback['document']->display_title }}</div>
                                    <div class="small text-muted">{{ $cleanReason }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($returnedDocs->isEmpty() && $plainDraftDocs->isEmpty() && $recentFeedbackComments->isEmpty())
                        <div class="text-center py-4">
                            <i class="ti ti-mood-smile fs-1 text-success"></i>
                            <p class="text-muted mt-2 mb-0">Great flow. No pending revisions or drafts right now.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="ti ti-list-check me-2"></i>Action Log Progress</h5>
                    @if(!empty($actionLogProgress['currentDraftId'] ?? null))
                        <a href="{{ route('action-logs.edit', $actionLogProgress['currentDraftId']) }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-edit me-1"></i> Update Current Week
                        </a>
                    @endif
                </div>
                <div class="card-body">
                    @php
                        $activeTasks = $actionLogProgress['activeTasks'] ?? [];
                    @endphp
                    @if(!empty($activeTasks))
                        <div class="accordion" id="actionLogProgressAccordion">
                            @foreach($activeTasks as $idx => $taskData)
                                @php $collapseId = 'taskProgress' . $idx; @endphp
                                <div class="card mb-2 border">
                                    <div class="card-header bg-light p-2" id="heading{{ $collapseId }}">
                                        <button class="btn btn-link text-decoration-none w-100 text-start d-flex justify-content-between align-items-center" type="button" data-toggle="collapse" data-target="#{{ $collapseId }}" aria-expanded="{{ $idx === 0 ? 'true' : 'false' }}">
                                            <span class="text-dark"><strong>{{ $taskData['task'] }}</strong></span>
                                            @if(($taskData['latest_progress'] ?? 'Not Started') === 'In Progress')
                                                <span class="badge bg-info">{{ $taskData['latest_progress'] }}</span>
                                            @elseif(($taskData['latest_progress'] ?? 'Not Started') === 'Done')
                                                <span class="badge bg-success">{{ $taskData['latest_progress'] }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $taskData['latest_progress'] }}</span>
                                            @endif
                                        </button>
                                    </div>
                                    <div id="{{ $collapseId }}" class="collapse {{ $idx === 0 ? 'show' : '' }}" data-parent="#actionLogProgressAccordion">
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Week</th>
                                                            <th>Progress</th>
                                                            <th>Due Date</th>
                                                            <th>Compilation Date</th>
                                                            <th>Notes/Progress</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach(($taskData['history'] ?? []) as $historyRow)
                                                            <tr>
                                                                <td>
                                                                    <strong>{{ $historyRow['period_label'] ?? '' }}</strong><br>
                                                                    <small class="text-muted">{{ $historyRow['period_range'] ?? '' }}</small>
                                                                </td>
                                                                <td>{{ $historyRow['progress'] ?? 'Not Started' }}</td>
                                                                <td>{{ $historyRow['due_date'] ?: '-' }}</td>
                                                                <td>{{ $historyRow['compilation_date'] ?: '-' }}</td>
                                                                <td>{{ $historyRow['notes_progress'] ?: '-' }}</td>
                                                                <td>
                                                                    @if(!empty($historyRow['is_current_period']) && !empty($historyRow['document_hashid']))
                                                                        <a href="{{ route('action-logs.edit', $historyRow['document_hashid']) }}" class="btn btn-sm btn-outline-primary">
                                                                            <i class="ti ti-edit me-1"></i>Update
                                                                        </a>
                                                                    @else
                                                                        <a href="{{ route('action-logs.show', $historyRow['document_hashid']) }}" class="btn btn-sm btn-outline-secondary">
                                                                            <i class="ti ti-eye me-1"></i>View
                                                                        </a>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ti ti-check-circle fs-1 text-success"></i>
                            <p class="text-muted mt-2 mb-0">No active action log tasks assigned to you.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@if($shouldShowActionToast)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toastEl = document.getElementById('actionLogDashboardToast');
            if (!toastEl || typeof bootstrap === 'undefined' || !bootstrap.Toast) return;

            const toast = new bootstrap.Toast(toastEl, {
                autohide: false
            });
            toast.show();
        });
    </script>
@endif
