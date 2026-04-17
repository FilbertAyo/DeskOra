<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Document Details</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Details</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light">
                                <i class="ti ti-arrow-left me-1"></i> Back to Documents
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Info and Actions - Top Row -->
    <div class="row">
        <!-- Document Info Card -->
        <div class="col-md-6">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        Document Information &amp; Actions
                    </h6>
                    <div class="d-flex align-items-center flex-wrap gap-1">
                     
                        @if($document->state === 'draft')
                            <span class="badge bg-warning">
                                Draft
                            </span>
                        @else
                            <span class="badge bg-success">
                                Submitted
                            </span>
                        @endif
                        @if($document->isReturnedForRevision())
                            <span class="badge bg-danger">
                                Returned for Revision
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body py-2">
                   
                    <div class="small text-muted mb-1">
                        <span>Created by: <strong>{{ $document->user->name }}</strong></span>
                        @if($document->period)
                            <span class="mx-2">|</span>
                            <span>
                                Week <strong>{{ $document->period->week_number }}</strong> of <strong>{{ $document->period->year }}</strong>
                            </span>
                        @elseif($document->type === \App\Models\Document::TYPE_MONTHLY_REPORT && $document->month && $document->year)
                            <span class="mx-2">|</span>
                            <span>
                                <strong>{{ \Carbon\Carbon::create((int) $document->year, (int) $document->month, 1)->format('F Y') }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="btn-group mt-2 gap-1" role="group">
                        @if($document->canBeEdited())
                            <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-primary btn-sm">
                                Edit
                            </a>
                        @endif

                        @if($document->canBeDeleted())
                            <form action="{{ route('documents.destroy', $document->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    Delete
                                </button>
                            </form>
                        @endif

                        @can('reject', $document)
                            <button
                                type="button"
                                class="btn btn-warning btn-sm text-dark"
                                data-bs-toggle="modal"
                                data-bs-target="#rejectDocumentModal">
                                <i class="ti ti-arrow-back-up me-1"></i> Return to Draft
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    

    <!-- Related Document Card -->
    @if($relatedDocument || $relatedAction)
            <div class="col-6">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="ti ti-link me-2"></i>
                            Related {{ $document->type === \App\Models\Document::TYPE_WEEKLY_PLAN ? 'Weekly Report' : 'Weekly Plan' }}
                        </h6>
                        @if($relatedDocument)
                            @if($relatedDocument->state === 'draft')
                                <span class="badge bg-warning">
                                    <i class="ti ti-edit me-1"></i>Draft
                                </span>
                            @else
                                <span class="badge bg-success">
                                    <i class="ti ti-check-circle me-1"></i>Submitted
                                </span>
                            @endif
                        @else
                            <span class="badge bg-secondary">
                                <i class="ti ti-alert-triangle me-1"></i>Not Created
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($relatedDocument)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2">
                                    <h6 class="mb-1">{{ $relatedDocument->display_title }}</h6>
                                    <small class="text-muted">
                                        <i class="ti ti-user me-1"></i>{{ $relatedDocument->user->name }} • 
                                        <i class="ti ti-calendar me-1"></i>{{ $relatedDocument->created_at->format('M d, Y') }}
                                    </small>
                                </div>
                                <div class="btn-group">
                                    <a href="{{ route('documents.show', $relatedDocument->id) }}" class="btn btn-info btn-sm text-white">
                                        <i class="ti ti-eye me-1"></i> View
                                    </a>
                                    @if($relatedDocument->canBeEdited())
                                        <a href="{{ route('documents.edit', $relatedDocument->id) }}" class="btn btn-primary btn-sm">
                                            <i class="ti ti-edit me-1"></i> Edit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif($relatedAction)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                   <h6 class="mb-0">{{ $relatedAction['label'] }}</h6>
                                    <small class="text-muted">{{ $relatedAction['description'] ?? '' }}</small>
                                </div>
                                <a href="{{ $relatedAction['route'] }}" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-plus-circle me-1"></i> Create
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
       
    @endif

    </div>

    @if($document->isReturnedForRevision() && $document->latestRejectionReason())
        <div class="alert alert-warning border mb-4">
            <h6 class="mb-2">
                <i class="ti ti-alert-triangle me-1"></i> Review Update
            </h6>
            <p class="mb-0">{{ $document->latestRejectionReason() }}</p>
        </div>
    @endif

    <!-- Main Target Section (for Weekly Reports) -->
    @if($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
    <div class="row">
        <div class="col-12">
            <!-- Weekly Report: Show Plan's Main Target and Accomplishment Status -->
            @php
                $relatedPlan = \App\Models\Document::where('user_id', $document->user_id)
                    ->where('period_id', $document->period_id)
                    ->where('type', \App\Models\Document::TYPE_WEEKLY_PLAN)
                    ->first();
            @endphp
            
            <div class="card shadow-none border border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="ti ti-target me-2"></i>Main Target Accomplishment Status
                    </h6>
                </div>
                <div class="card-body">
                    @if($relatedPlan && $relatedPlan->main_target)
                        <div class="mb-3">
                            <label class="form-label text-muted font-weight-bold">Main Target from Weekly Plan:</label>
                            <div class="alert alert-light border">
                                <p class="mb-0"><strong>{{ $relatedPlan->main_target }}</strong></p>
                            </div>
                        </div>
                    @endif

                    @if($document->target_accomplished !== null)
                        <div class="mb-3">
                            <label class="form-label text-muted font-weight-bold">Accomplishment Status:</label>
                            <div>
                                @if($document->target_accomplished)
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        <i class="ti ti-check-circle me-1"></i>Yes, Completed
                                    </span>
                                @else
                                    <span class="badge bg-danger rounded-pill px-3 py-2">
                                        <i class="ti ti-x-circle me-1"></i>No, Not Completed
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if(!$document->target_accomplished && $document->target_explanation)
                            <div class="mb-0">
                                <label class="form-label text-muted font-weight-bold">Explanation:</label>
                                <div class="alert alert-warning border">
                                    <p class="mb-0">{{ $document->target_explanation }}</p>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="alert alert-secondary">
                            <i class="ti ti-info me-2"></i>
                            Accomplishment status has not been set for this report.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- PDF Viewer - Full Width -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-file-text me-2"></i>{{ $document->display_title }}
                    </h5>
                    @if($document->file_path && $document->hasFile())
                        <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info">
                            <i class="ti ti-download me-1"></i> Download PDF
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($document->file_path && $document->hasFile())
                        <!-- PDF Viewer -->
                        <div style="min-height: 800px;">
                            <iframe 
                                src="{{ $document->file_url }}" 
                                style="width: 100%; height: 800px; border: none;"
                                type="application/pdf">
                                <p>Your browser does not support PDFs. 
                                    <a href="{{ $document->file_url }}" target="_blank">Download the PDF</a> instead.
                                </p>
                            </iframe>
                        </div>
                    @else
                        @php
                            $content = is_array($document->content) ? $document->content : [];
                        @endphp

                        @if($document->type === \App\Models\Document::TYPE_MONTHLY_REPORT)
                            @php
                                $mplanned = is_array($content['planned'] ?? null) ? $content['planned'] : [];
                                $moff = is_array($content['off_plan'] ?? null) ? $content['off_plan'] : [];
                                $msum = trim((string) ($content['month_summary'] ?? ''));
                                $ypIds = collect($mplanned)->pluck('year_plan_activity_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
                                $ypActivities = $ypIds === []
                                    ? collect()
                                    : \App\Models\YearPlanActivity::with(['department.parent'])->whereIn('id', $ypIds)->get()->keyBy('id');
                                $monthTitle = \Carbon\Carbon::create((int) $document->year, (int) $document->month, 1)->format('F Y');
                            @endphp
                            <div class="p-4">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-0">Monthly report — {{ $monthTitle }}</h5>
                                        <small class="text-muted">Year plan alignment and other work</small>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="{{ route('year-plan.show', $document->year) }}" class="btn btn-sm btn-outline-primary">Year plan {{ $document->year }}</a>
                                        <a href="{{ route('documents.index', ['type' => 'monthly_report']) }}" class="btn btn-sm btn-light">Monthly archive</a>
                                    </div>
                                </div>

                                @if($msum !== '')
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-notes me-2"></i>Month summary</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-0" style="white-space: pre-wrap;">{{ $msum }}</div>
                                        </div>
                                    </div>
                                @endif

                                @if(count($mplanned) > 0)
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-chart-line me-2"></i>Year plan activities (this month)</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0 align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Code</th>
                                                            <th>Activity</th>
                                                            <th>Status</th>
                                                            <th>Achievements</th>
                                                            <th>Challenges</th>
                                                            <th>Next steps</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($mplanned as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            @php $act = $ypActivities->get((int) ($row['year_plan_activity_id'] ?? 0)); @endphp
                                                            <tr>
                                                                <td class="fw-semibold">{{ $act ? $act->displayCode() : '—' }}</td>
                                                                <td>
                                                                    @if($act)
                                                                        @if($act->department?->parent)
                                                                            <span class="text-muted small">{{ $act->department->parent->name }}</span><br>
                                                                        @endif
                                                                        <span class="fw-medium">{{ $act->department?->name ?? '—' }}</span>
                                                                        <p class="small text-muted mb-0 mt-1">{{ $act->description }}</p>
                                                                    @else
                                                                        <span class="text-muted">Activity #{{ $row['year_plan_activity_id'] ?? '—' }}</span>
                                                                    @endif
                                                                </td>
                                                                <td>{{ $row['implementation_status'] ?? '—' }}</td>
                                                                <td>{!! nl2br(e($row['achievements'] ?? '')) !!}</td>
                                                                <td>{!! nl2br(e($row['challenges'] ?? '')) !!}</td>
                                                                <td>{!! nl2br(e($row['next_steps'] ?? '')) !!}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if(count($moff) > 0)
                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-plus me-2"></i>Work outside the year plan</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Title</th>
                                                            <th>Description</th>
                                                            <th>Status</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($moff as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td>{{ $row['title'] ?? '' }}</td>
                                                                <td>{!! nl2br(e($row['description'] ?? '')) !!}</td>
                                                                <td>{{ $row['status'] ?? '' }}</td>
                                                                <td>{!! nl2br(e($row['notes'] ?? '')) !!}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @if($msum === '' && count($mplanned) === 0 && count($moff) === 0)
                                    <div class="alert alert-light border">
                                        Structured report fields are empty. @if($document->file_path)Use the PDF tab above if this was filed as a document upload.@endif
                                    </div>
                                @endif

                                @if($document->attachment)
                                    <p class="small text-muted mb-0">
                                        <i class="ti ti-paperclip me-1"></i>
                                        <a href="{{ asset('storage/' . $document->attachment) }}" target="_blank">Supporting attachment</a>
                                    </p>
                                @endif
                            </div>
                        @elseif(in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT]) && !empty($content))
                            @if($document->type === \App\Models\Document::TYPE_WEEKLY_PLAN)
                                @php
                                    $objective = $content['objective'] ?? [];
                                    $plannedActivities = $content['planned_activities'] ?? [];
                                    $keyMeetings = $content['key_meetings'] ?? [];
                                    $budget = $content['budget'] ?? [];
                                    $plannedTeamIds = collect($plannedActivities)
                                        ->filter(fn($row) => is_array($row) && !empty($row['team_member_ids']) && is_array($row['team_member_ids']))
                                        ->flatMap(fn($row) => $row['team_member_ids'])
                                        ->filter()
                                        ->map(fn($id) => (int)$id)
                                        ->unique()
                                        ->values()
                                        ->all();
                                    $teamUserNames = \App\Models\User::whereIn('id', $plannedTeamIds)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                    $dayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    $normalizedActivities = collect($plannedActivities ?: [])
                                        ->map(function ($row) {
                                            if (!is_array($row)) {
                                                return null;
                                            }
                                            $day = trim((string) ($row['day'] ?? ''));
                                            $activity = trim((string) ($row['activity'] ?? ''));
                                            $location = trim((string) ($row['location'] ?? ''));
                                            $notes = trim((string) ($row['notes_inclusion'] ?? ''));
                                            $teamIds = is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [];

                                            if ($day === '' && $activity === '' && $location === '' && $notes === '' && empty($teamIds)) {
                                                return null;
                                            }

                                            return [
                                                'day' => $day,
                                                'activity' => $activity,
                                                'location' => $location,
                                                'notes_inclusion' => $notes,
                                                'team_member_ids' => $teamIds,
                                            ];
                                        })
                                        ->filter()
                                        ->values();
                                    $groupedActivities = collect($dayOrder)
                                        ->mapWithKeys(fn ($day) => [$day => []])
                                        ->merge(
                                            $normalizedActivities
                                                ->groupBy(function ($row) use ($dayOrder) {
                                                    return in_array($row['day'], $dayOrder, true) ? $row['day'] : 'Other';
                                                })
                                                ->toArray()
                                        );

                                    $hasKeyMeetings = collect($keyMeetings ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $k) {
                                            if (trim((string) ($row[$k] ?? '')) !== '') {
                                                return true;
                                            }
                                        }

                                        return false;
                                    });

                                    $hasBudgetRows = collect($budget ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['item', 'description_purpose', 'amount'] as $k) {
                                            if (trim((string) ($row[$k] ?? '')) !== '') {
                                                return true;
                                            }
                                        }

                                        return false;
                                    });
                                @endphp

                                <div class="p-4">
                                    <div class="card shadow-none border border-primary mb-4">
                                        <div class="card-header text-white">
                                            <h6 class="mb-0"><i class="ti ti-target me-2"></i>Objective / Main Target</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Main Target</th>
                                                            <th style="width: 150px;">Status</th>
                                                            <th>Reflection / Progress</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>{{ $document->main_target ?: '-' }}</td>
                                                            <td>{{ $objective['status'] ?? '-' }}</td>
                                                            <td>{{ $objective['reflection_progress'] ?? '-' }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-calendar me-2"></i>Planned Activities</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 90px;">Day</th>
                                                            <th>Activity</th>
                                                            <th style="width: 180px;">Location</th>
                                                            <th style="width: 180px;">Lead Staff/Team</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $hasGroupedRows = false; @endphp
                                                        @foreach($groupedActivities as $day => $rows)
                                                            @if(!empty($rows))
                                                                @php $hasGroupedRows = true; @endphp
                                                                @foreach($rows as $idx => $row)
                                                                    <tr>
                                                                        @if($idx === 0)
                                                                            <td class="fw-semibold align-middle" rowspan="{{ count($rows) }}">{{ $day }}</td>
                                                                        @endif
                                                                        <td>{{ $row['activity'] ?? '' }}</td>
                                                                        <td>{{ $row['location'] ?? '' }}</td>
                                                                        <td>
                                                                            @php
                                                                                $rowTeamIds = (is_array($row['team_member_ids'] ?? null)) ? $row['team_member_ids'] : [];
                                                                                $rowTeamNames = collect($rowTeamIds)
                                                                                    ->map(fn($id) => $teamUserNames[(int)$id] ?? null)
                                                                                    ->filter()
                                                                                    ->values()
                                                                                    ->all();
                                                                            @endphp
                                                                            {{ !empty($rowTeamNames) ? implode(', ', $rowTeamNames) : '' }}
                                                                        </td>
                                                                        <td>{{ $row['notes_inclusion'] ?? '' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            @endif
                                                        @endforeach
                                                        @if(!$hasGroupedRows)
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted py-4">No planned activities for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-users me-2"></i>Key Meetings / Engagements</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 140px;">Date</th>
                                                            <th>Meeting</th>
                                                            <th style="width: 200px;">Participants</th>
                                                            <th style="width: 180px;">Location</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($hasKeyMeetings)
                                                            @foreach(($keyMeetings ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
                                                                    $kmEmpty = true;
                                                                    foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $kmk) {
                                                                        if (trim((string) ($row[$kmk] ?? '')) !== '') {
                                                                            $kmEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!$kmEmpty)
                                                                    <tr>
                                                                        <td>{{ $row['date'] ?? '' }}</td>
                                                                        <td>{{ $row['meeting'] ?? '' }}</td>
                                                                        <td>{{ $row['participants'] ?? '' }}</td>
                                                                        <td>{{ $row['location'] ?? '' }}</td>
                                                                        <td>{{ $row['reactions_notes'] ?? '' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted py-4">No meetings or engagements planned for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-0">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>Budget</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Budget Item / Activity</th>
                                                            <th style="width: 360px;">Description / Purpose</th>
                                                            <th style="width: 180px;">Amount (TZS)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($hasBudgetRows)
                                                            @foreach(($budget ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
                                                                    $bEmpty = true;
                                                                    foreach (['item', 'description_purpose', 'amount'] as $bk) {
                                                                        if (trim((string) ($row[$bk] ?? '')) !== '') {
                                                                            $bEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!$bEmpty)
                                                                    <tr>
                                                                        <td>{{ $row['item'] ?? '' }}</td>
                                                                        <td>{{ $row['description_purpose'] ?? '' }}</td>
                                                                        <td>{{ $row['amount'] ?? '' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-4">No budget items for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($document->type === \App\Models\Document::TYPE_WEEKLY_REPORT)
                                @php
                                    $summary = $content['summary'] ?? '';
                                    $activityProgress = $content['activity_progress'] ?? [];
                                    $keyAchievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
                                    $challengesMitigation = $content['challenges_mitigation'] ?? [];
                                    $budgetStatuses = $content['budget_statuses'] ?? [];

                                    $relatedPlan = \App\Models\Document::where('user_id', $document->user_id)
                                        ->where('period_id', $document->period_id)
                                        ->where('type', \App\Models\Document::TYPE_WEEKLY_PLAN)
                                        ->first();
                                    $planBudget = is_array($relatedPlan?->content ?? null) ? ($relatedPlan->content['budget'] ?? []) : [];

                                    $hasActivityProgress = collect($activityProgress ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['activity_output', 'progress', 'remarks_challenges', 'next_step'] as $k) {
                                            if (trim((string) ($row[$k] ?? '')) !== '') {
                                                return true;
                                            }
                                        }

                                        return false;
                                    });

                                    $hasChallengesMitigation = collect($challengesMitigation ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['challenge', 'action_taken_planned'] as $k) {
                                            if (trim((string) ($row[$k] ?? '')) !== '') {
                                                return true;
                                            }
                                        }

                                        return false;
                                    });
                                @endphp

                                <div class="p-4">
                                    @if($summary)
                                        <div class="card shadow-none border border-secondary mb-4">
                                            <div class="card-header bg-white border-bottom">
                                                <h6 class="mb-0"><i class="ti ti-book-open me-2"></i>Summary of the Week</h6>
                                            </div>
                                            <div class="card-body">
                                                <div>{{ $summary }}</div>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-list me-2"></i>Weekly Activity Progress</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 70px;">No.</th>
                                                            <th>Activity/Output</th>
                                                            <th style="width: 160px;">Progress</th>
                                                            <th>Remarks/Challenges</th>
                                                            <th style="width: 180px;">Next Step</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($hasActivityProgress)
                                                            @php $apNum = 0; @endphp
                                                            @foreach(($activityProgress ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
                                                                    $apEmpty = true;
                                                                    foreach (['activity_output', 'progress', 'remarks_challenges', 'next_step'] as $apk) {
                                                                        if (trim((string) ($row[$apk] ?? '')) !== '') {
                                                                            $apEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!$apEmpty)
                                                                    @php $apNum++; @endphp
                                                                    <tr>
                                                                        <td class="text-center">{{ $apNum }}</td>
                                                                        <td>{{ $row['activity_output'] ?? '' }}</td>
                                                                        <td>{{ $row['progress'] ?? '' }}</td>
                                                                        <td>{{ $row['remarks_challenges'] ?? '' }}</td>
                                                                        <td>{{ $row['next_step'] ?? '' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted py-4">No activity progress recorded for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-check-square me-2"></i>Key Achievements</h6>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $achievementRows = array_values(array_filter(array_map(function ($item) {
                                                    return is_string($item) ? trim($item) : '';
                                                }, $keyAchievements)));
                                            @endphp
                                            @if(count($achievementRows) > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-bordered mb-0">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th style="width: 70px;">No.</th>
                                                                <th>Achievement</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($achievementRows as $idx => $achievement)
                                                                <tr>
                                                                    <td class="text-center">{{ $idx + 1 }}</td>
                                                                    <td>{{ $achievement }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="text-muted py-2">No key achievements recorded for this week.</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-4">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-alert-triangle me-2"></i>Challenges & Mitigation</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Challenge(s)</th>
                                                            <th style="width: 360px;">Action Taken/Planned</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($hasChallengesMitigation)
                                                            @foreach(($challengesMitigation ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
                                                                    $cmEmpty = true;
                                                                    foreach (['challenge', 'action_taken_planned'] as $cmk) {
                                                                        if (trim((string) ($row[$cmk] ?? '')) !== '') {
                                                                            $cmEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!$cmEmpty)
                                                                    <tr>
                                                                        <td>{{ $row['challenge'] ?? '' }}</td>
                                                                        <td>{{ $row['action_taken_planned'] ?? '' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="2" class="text-center text-muted py-4">No challenges or mitigation notes for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card shadow-none border mb-0">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>Budget Status (from weekly plan)</h6>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $planBudgetFiltered = is_array($planBudget) ? array_values(array_filter($planBudget, function ($row) {
                                                    if (!is_array($row)) return false;
                                                    return trim((string)($row['item'] ?? '')) !== '' || trim((string)($row['amount'] ?? '')) !== '';
                                                })) : [];
                                                $reviewsByUid = $relatedPlan ? $relatedPlan->budgetReviewItemsByUid() : [];
                                                // Index budget_statuses by budget_uid
                                                $statusByUid = [];
                                                foreach ($budgetStatuses as $sIdx => $s) {
                                                    $sUid = is_array($s) ? trim((string)($s['budget_uid'] ?? '')) : '';
                                                    if ($sUid !== '') $statusByUid[$sUid] = $s;
                                                }
                                            @endphp
                                            @if(count($planBudgetFiltered) > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Budget Item</th>
                                                                <th style="width: 150px;">Amount</th>
                                                                <th style="width: 130px;">Review</th>
                                                                <th style="width: 160px;">Staff Status</th>
                                                                <th>Notes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($planBudgetFiltered as $i => $planRow)
                                                                @php
                                                                    $planRow = is_array($planRow) ? $planRow : [];
                                                                    $uid = trim((string)($planRow['budget_uid'] ?? ''));
                                                                    $review = ($uid !== '' && isset($reviewsByUid[$uid])) ? $reviewsByUid[$uid] : null;
                                                                    $reviewDecision = is_array($review) ? ($review['decision'] ?? null) : null;
                                                                    $statusRow = ($uid !== '' && isset($statusByUid[$uid])) ? $statusByUid[$uid] : ($budgetStatuses[$i] ?? []);
                                                                    $statusRow = is_array($statusRow) ? $statusRow : [];
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $planRow['item'] ?? '' }}</td>
                                                                    <td>{{ $planRow['amount'] ?? '' }}</td>
                                                                    <td class="text-center">
                                                                        @if($reviewDecision === 'approved')
                                                                            <span class="badge bg-success">Approved</span>
                                                                        @elseif($reviewDecision === 'rejected')
                                                                            <span class="badge bg-danger">Rejected</span>
                                                                        @elseif($reviewDecision === 'pending')
                                                                            <span class="badge bg-warning">Pending</span>
                                                                        @else
                                                                            <span class="text-muted">-</span>
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ $statusRow['status'] ?? '' }}</td>
                                                                    <td>{{ $statusRow['notes'] ?? '' }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="alert alert-warning mb-0">
                                                    No budget items found in the weekly plan for this period.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="alert alert-warning m-4">
                                <i class="ti ti-alert-triangle me-2"></i>
                                <strong>No document content available.</strong>
                                @if($document->canBeEdited())
                                    Please <a href="{{ route('documents.edit', $document->id) }}">complete the online form</a> to view the document.
                                @else
                                    The filled content for this document is not available.
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    @can('reject', $document)
        <div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-labelledby="rejectDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('documents.reject', $document->id) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectDocumentModalLabel">
                                Return {{ $document->type_name }} to Draft
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-2">
                                Provide a clear reason so staff can update and resubmit correctly.
                            </p>
                            <label for="rejectReason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <textarea
                                class="form-control @error('reason') is-invalid @enderror"
                                id="rejectReason"
                                name="reason"
                                rows="4"
                                maxlength="2000"
                                required>{{ old('reason') }}</textarea>
                            @error('reason')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning text-dark">
                                <i class="ti ti-arrow-back-up me-1"></i> Return to Draft
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if($errors->has('reason'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const rejectModalEl = document.getElementById('rejectDocumentModal');
                    if (rejectModalEl && window.bootstrap && bootstrap.Modal) {
                        const rejectModal = new bootstrap.Modal(rejectModalEl);
                        rejectModal.show();
                    }
                });
            </script>
        @endif
    @endcan
</x-app-layout>
