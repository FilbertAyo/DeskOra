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
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="ti ti-info me-2"></i>Document Information &amp; Actions
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="d-flex align-items-center flex-wrap mb-2">
                        <span class="badge bg-info mr-2 mb-1">
                            <i class="ti ti-file me-1"></i>{{ $document->type_name }}
                        </span>
                        @if($document->state === 'draft')
                            <span class="badge bg-warning mr-2 mb-1">
                                <i class="ti ti-edit me-1"></i>Draft
                            </span>
                        @else
                            <span class="badge bg-success mr-2 mb-1">
                                <i class="ti ti-check-circle me-1"></i>Submitted
                            </span>
                        @endif
                        @if($document->isReturnedForRevision())
                            <span class="badge bg-danger mr-2 mb-1">
                                <i class="ti ti-arrow-back-up me-1"></i>Returned for Revision
                            </span>
                        @endif
                    </div>
                    <div class="small text-muted mb-1">
                        <span>Created by: <strong>{{ $document->user->name }}</strong></span>
                        @if($document->period)
                            <span class="mx-2">|</span>
                            <span>
                                Week <strong>{{ $document->period->week_number }}</strong> of <strong>{{ $document->period->year }}</strong>
                            </span>
                        @endif
                    </div>
                    <div class="btn-group mt-2" role="group">
                        @if($document->canBeEdited())
                            <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-primary btn-sm">
                                <i class="ti ti-edit me-1"></i> Edit
                            </a>
                        @endif

                        @if($document->canBeDeleted())
                            <form action="{{ route('documents.destroy', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="ti ti-trash-2 me-1"></i> Delete
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
                                    <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($relatedDocument->id)) }}" class="btn btn-info btn-sm text-white">
                                        <i class="ti ti-eye me-1"></i> View
                                    </a>
                                    @if($relatedDocument->canBeEdited())
                                        <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($relatedDocument->id)) }}" class="btn btn-primary btn-sm">
                                            <i class="ti ti-edit me-1"></i> Edit
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @elseif($relatedAction)
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <div class="mb-2">
                                    <p class="text-muted mb-1">No related document found.</p>
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

                        @if(in_array($document->type, [\App\Models\Document::TYPE_WEEKLY_PLAN, \App\Models\Document::TYPE_WEEKLY_REPORT]) && !empty($content))
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
                                                            <th>Description</th>
                                                            <th style="width: 150px;">Status</th>
                                                            <th>Reflection / Progress</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>{{ $document->main_target ?: '-' }}</td>
                                                            <td>{{ $objective['description'] ?? '-' }}</td>
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
                                                                <td colspan="5" class="text-center text-muted">No planned activities provided.</td>
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
                                                        @foreach(($keyMeetings ?: []) as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td>{{ $row['date'] ?? '' }}</td>
                                                                <td>{{ $row['meeting'] ?? '' }}</td>
                                                                <td>{{ $row['participants'] ?? '' }}</td>
                                                                <td>{{ $row['location'] ?? '' }}</td>
                                                                <td>{{ $row['reactions_notes'] ?? '' }}</td>
                                                            </tr>
                                                        @endforeach
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
                                                        @foreach(($budget ?: []) as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td>{{ $row['item'] ?? '' }}</td>
                                                                <td>{{ $row['description_purpose'] ?? '' }}</td>
                                                                <td>{{ $row['amount'] ?? '' }}</td>
                                                            </tr>
                                                        @endforeach
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
                                                        @foreach(($activityProgress ?: []) as $idx => $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td class="text-center">{{ $idx + 1 }}</td>
                                                                <td>{{ $row['activity_output'] ?? '' }}</td>
                                                                <td>{{ $row['progress'] ?? '' }}</td>
                                                                <td>{{ $row['remarks_challenges'] ?? '' }}</td>
                                                                <td>{{ $row['next_step'] ?? '' }}</td>
                                                            </tr>
                                                        @endforeach
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
                                                <div class="text-muted">No achievements provided.</div>
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
                                                        @foreach(($challengesMitigation ?: []) as $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td>{{ $row['challenge'] ?? '' }}</td>
                                                                <td>{{ $row['action_taken_planned'] ?? '' }}</td>
                                                            </tr>
                                                        @endforeach
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
                                            @if(!empty($planBudget))
                                                <div class="table-responsive">
                                                    <table class="table table-bordered">
                                                        <thead class="thead-light">
                                                            <tr>
                                                                <th>Budget Item</th>
                                                                <th style="width: 180px;">Amount</th>
                                                                <th style="width: 220px;">Status</th>
                                                                <th>Notes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @php
                                                                $max = max(count($planBudget), count($budgetStatuses));
                                                            @endphp
                                                            @for($i=0;$i<$max;$i++)
                                                                @php
                                                                    $planRow = $planBudget[$i] ?? [];
                                                                    $statusRow = $budgetStatuses[$i] ?? [];
                                                                    $planRow = is_array($planRow) ? $planRow : [];
                                                                    $statusRow = is_array($statusRow) ? $statusRow : [];
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $planRow['item'] ?? '' }}</td>
                                                                    <td>{{ $planRow['amount'] ?? '' }}</td>
                                                                    <td>{{ $statusRow['status'] ?? '' }}</td>
                                                                    <td>{{ $statusRow['notes'] ?? '' }}</td>
                                                                </tr>
                                                            @endfor
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
                                    Please <a href="{{ route('documents.edit', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}">complete the online form</a> to view the document.
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

    <!-- Comments Section -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-none border">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="ti ti-message-circle me-2"></i>
                        Comments
                        <span class="badge bg-primary rounded-pill ml-2">{{ $document->comments->count() }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Comment Form (Only for Reviewers) -->
                    @can('create', \App\Models\Comment::class)
                        <div class="mb-4">
                            <form action="{{ route('comments.store', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" method="POST" id="commentForm">
                                @csrf
                                <div class="form-group">
                                    <label for="comment" class="form-label">
                                        <i class="ti ti-edit me-1"></i> Add Comment
                                    </label>
                                    <textarea 
                                        class="form-control @error('comment') is-invalid @enderror" 
                                        name="comment" 
                                        id="comment" 
                                        rows="3" 
                                        placeholder="Enter your comment here..."
                                        required>{{ old('comment') }}</textarea>
                                    @error('comment')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-send me-1"></i> Post Comment
                                </button>
                            </form>
                        </div>
                        <hr>
                    @endcan

                    <!-- Comments List -->
                    @if($document->comments->count() > 0)
                        <div class="comments-list">
                            @foreach($document->comments as $comment)
                                <div class="comment-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-2" style="width: 32px; height: 32px;">
                                                {{ strtoupper(substr($comment->user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong class="d-block">{{ $comment->user->name }}</strong>
                                                <small class="text-muted">
                                                    <i class="ti ti-clock me-1"></i>
                                                    {{ $comment->created_at->diffForHumans() }}
                                                    @if($comment->updated_at != $comment->created_at)
                                                        <span class="text-muted">(edited)</span>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                        @can('update', $comment)
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" 
                                                    class="btn btn-sm btn-light edit-comment-btn" 
                                                    data-comment-id="{{ $comment->id }}"
                                                    data-comment-text="{{ htmlspecialchars($comment->comment, ENT_QUOTES, 'UTF-8') }}"
                                                    title="Edit">
                                                    <i class="ti ti-edit"></i>
                                                </button>
                                                <form action="{{ route('comments.destroy', \Vinkla\Hashids\Facades\Hashids::encode($comment->id)) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Delete">
                                                        <i class="ti ti-trash-2"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @endcan
                                    </div>
                                    <div class="comment-content">
                                        @php
                                            $isRejectComment = \Illuminate\Support\Str::startsWith(
                                                $comment->comment,
                                                \App\Models\Document::REVIEW_REJECTION_PREFIX
                                            );
                                            $displayComment = $isRejectComment
                                                ? trim(\Illuminate\Support\Str::after($comment->comment, \App\Models\Document::REVIEW_REJECTION_PREFIX))
                                                : $comment->comment;
                                        @endphp
                                        @if($isRejectComment)
                                            <div class="mb-1">
                                                <span class="badge bg-warning text-dark">
                                                    <i class="ti ti-arrow-back-up me-1"></i>Returned to Draft
                                                </span>
                                            </div>
                                        @endif
                                        <p class="mb-0" id="comment-text-{{ $comment->id }}">{{ $displayComment }}</p>
                                        
                                        <!-- Edit Form (Hidden by default) -->
                                        <div id="edit-form-{{ $comment->id }}" style="display: none;" class="mt-2">
                                            <form action="{{ route('comments.update', \Vinkla\Hashids\Facades\Hashids::encode($comment->id)) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <textarea class="form-control mb-2" name="comment" rows="3" required>{{ $comment->comment }}</textarea>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="ti ti-check me-1"></i> Save
                                                    </button>
                                                    <button type="button" 
                                                        class="btn btn-secondary btn-sm cancel-edit-btn" 
                                                        data-comment-id="{{ $comment->id }}"
                                                        data-comment-text="{{ htmlspecialchars($comment->comment, ENT_QUOTES, 'UTF-8') }}">
                                                        <i class="ti ti-x me-1"></i> Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="ti ti-message-circle fs-1 text-muted"></i>
                            <p class="text-muted mb-0 mt-2">No comments yet</p>
                            @can('create', \App\Models\Comment::class)
                                <small class="text-muted">Be the first to comment!</small>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle edit comment button clicks
            document.querySelectorAll('.edit-comment-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    document.getElementById('comment-text-' + commentId).style.display = 'none';
                    document.getElementById('edit-form-' + commentId).style.display = 'block';
                });
            });

            // Handle cancel edit button clicks
            document.querySelectorAll('.cancel-edit-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const commentId = this.getAttribute('data-comment-id');
                    const originalText = this.getAttribute('data-comment-text');
                    document.getElementById('comment-text-' + commentId).style.display = 'block';
                    document.getElementById('edit-form-' + commentId).style.display = 'none';
                    const textarea = document.querySelector('#edit-form-' + commentId + ' textarea');
                    if (textarea) {
                        textarea.value = originalText;
                    }
                });
            });
        });
    </script>

    @can('reject', $document)
        <div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-labelledby="rejectDocumentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('documents.reject', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}">
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
