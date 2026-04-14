<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Create New Document</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
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



    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('documents.store') }}" id="createDocumentForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="action" id="hiddenAction" value="draft">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <h5 class="mb-0"><i class="ti ti-file-text me-2"></i>Document Information</h5>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <small class="text-muted">Fill the form below and save draft or submit.</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Document Type -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="type" class="form-label">
                                        Document Type <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                       
                                        <select class="form-control @error('type') is-invalid @enderror"
                                            name="type"
                                            id="type"
                                            {{ isset($selectedType) ? 'disabled' : '' }}
                                            required>
                                            <option value="" disabled {{ (old('type') || isset($selectedType)) ? '' : 'selected' }}>
                                                -- Select Document Type --
                                            </option>
                                            @foreach($availableTypes as $type => $name)
                                                <option value="{{ $type }}" 
                                                    {{ old('type', $selectedType ?? '') == $type ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(isset($selectedType))
                                            <input type="hidden" name="type" value="{{ $selectedType }}">
                                        @endif
                                    </div>
                                    @error('type')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        @if(isset($selectedType))
                                            Document type is set to: <strong>{{ $availableTypes[$selectedType] ?? $selectedType }}</strong>
                                        @else
                                            Select the type of document you want to create
                                        @endif
                                    </small>
                                </div>
                            </div>

                            <!-- Work Period (for weekly plans and reports) -->
                            <div class="col-md-6 mb-3" id="workPeriodContainer" @if(!isset($selectedType) || !in_array($selectedType, ['weekly_plan', 'weekly_report'])) style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="period_id" class="form-label">
                                        Work Period <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                      
                                        <select class="form-control @error('period_id') is-invalid @enderror"
                                            name="period_id"
                                            id="period_id" disabled>
                                            <option value="">-- Select Work Period --</option>
                                            @foreach($workPeriods as $workPeriod)
                                                <option value="{{ $workPeriod->id }}" 
                                                    {{ old('period_id', $defaultPeriodId ?? '') == $workPeriod->id ? 'selected' : '' }}>
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
                                    <small class="form-text text-muted">Select the work period for this document</small>
                                </div>
                            </div>

                            <!-- Month and Year (for monthly reports) -->
                            <div class="col-md-6 mb-3" id="monthYearContainer" @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="month" class="form-label">
                                        Month <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control js-monthly-report-reload @error('month') is-invalid @enderror"
                                            name="month"
                                            id="month">
                                            <option value="">-- Select Month --</option>
                                            @php
                                                $months = [
                                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                                ];
                                                $selectedMonth = old('month', $monthlyDefaultMonth ?? (int) date('n'));
                                            @endphp
                                            @foreach($months as $num => $name)
                                                <option value="{{ $num }}" {{ (int) $selectedMonth === (int) $num ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('month')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Changing month or year reloads planned activities from the year plan.</small>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3" id="yearContainer" @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="year" class="form-label">
                                        Year <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control js-monthly-report-reload @error('year') is-invalid @enderror"
                                            name="year"
                                            id="year">
                                            <option value="">-- Select Year --</option>
                                            @php
                                                $selectedYear = old('year', $monthlyDefaultYear ?? (int) date('Y'));
                                                $yearChoices = isset($monthlyYearChoices) ? $monthlyYearChoices : collect(range((int) date('Y') + 1, (int) date('Y') - 5));
                                            @endphp
                                            @foreach($yearChoices as $y)
                                                <option value="{{ $y }}" {{ (int) $selectedYear === (int) $y ? 'selected' : '' }}>
                                                    {{ $y }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('year')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Calendar year of the annual plan</small>
                                </div>
                            </div>

                            @if(isset($selectedType) && $selectedType === 'monthly_report')
                            @php
                                $mContent = old('content', []);
                                $mPlannedOld = is_array($mContent['planned'] ?? null) ? $mContent['planned'] : [];
                                $mOffPlanOld = is_array($mContent['off_plan'] ?? null) ? $mContent['off_plan'] : [['title' => '', 'description' => '', 'status' => '', 'notes' => '']];
                                if (count($mOffPlanOld) === 0) {
                                    $mOffPlanOld = [['title' => '', 'description' => '', 'status' => '', 'notes' => '']];
                                }
                                $implStatuses = ['Not started', 'In progress', 'Completed', 'Deferred', 'Not applicable'];
                                $reportMonthName = \Carbon\Carbon::create((int) ($monthlyDefaultYear ?? date('Y')), (int) ($monthlyDefaultMonth ?? date('n')), 1)->format('F');
                            @endphp
                            <div class="col-12 mb-3" id="monthlyReportFormSection">
                                <div class="card border-primary shadow-none">
                                    <div class="card-header bg-white border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <h6 class="mb-0"><i class="ti ti-chart-line me-2"></i>Monthly report vs year plan</h6>
                                            <small class="text-muted">{{ $reportMonthName }} {{ $monthlyDefaultYear ?? '' }} — activities planned for this month in your sub-departments</small>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if($monthlyYearPlan)
                                                <a href="{{ route('year-plan.show', $monthlyYearPlan->year) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="ti ti-external-link me-1"></i> Open year plan {{ $monthlyYearPlan->year }}
                                                </a>
                                            @else
                                                <a href="{{ route('year-plan.index') }}" class="btn btn-sm btn-outline-secondary">
                                                    <i class="ti ti-chart-line me-1"></i> Year plans
                                                </a>
                                            @endif
                                            <a href="{{ route('documents.index', ['type' => 'monthly_report']) }}" class="btn btn-sm btn-light">
                                                <i class="ti ti-folder me-1"></i> Monthly archive
                                            </a>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        @if(!$monthlyYearPlan)
                                            <div class="alert alert-warning mb-3">
                                                There is no year plan for <strong>{{ $monthlyDefaultYear }}</strong>. Create one under Year Plan, or pick another year above.
                                            </div>
                                        @endif
                                        @if(empty($monthlyHasSubDepartments))
                                            <div class="alert alert-warning mb-3">
                                                You are not assigned to any <strong>sub-department</strong>. Ask an administrator to link you so planned activities appear here. You can still report <strong>off-plan</strong> work below.
                                            </div>
                                        @endif

                                        @if($monthlyPlannedActivities->isNotEmpty())
                                            <h6 class="text-primary mb-2">Planned activities (year plan)</h6>
                                            <div class="table-responsive mb-4">
                                                <table class="table table-bordered align-middle">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="min-width: 72px;">Code</th>
                                                            <th style="min-width: 200px;">Sub-department / activity</th>
                                                            <th style="min-width: 140px;">Status</th>
                                                            <th>Achievements / progress</th>
                                                            <th>Challenges</th>
                                                            <th>Next steps</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($monthlyPlannedActivities as $idx => $activity)
                                                            @php
                                                                $row = collect($mPlannedOld)->firstWhere('year_plan_activity_id', $activity->id);
                                                                $row = is_array($row) ? $row : [];
                                                            @endphp
                                                            <tr>
                                                                <td class="fw-semibold">{{ $activity->displayCode() }}</td>
                                                                <td>
                                                                    @if($activity->department?->parent)
                                                                        <span class="text-muted small">{{ $activity->department->parent->name }}</span><br>
                                                                    @endif
                                                                    <span class="fw-medium">{{ $activity->department?->name ?? '—' }}</span>
                                                                    <p class="small text-muted mb-0 mt-1">{{ \Illuminate\Support\Str::limit($activity->description, 220) }}</p>
                                                                    <input type="hidden" name="content[planned][{{ $idx }}][year_plan_activity_id]" value="{{ $activity->id }}">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select form-select-sm" name="content[planned][{{ $idx }}][implementation_status]">
                                                                        @foreach($implStatuses as $opt)
                                                                            <option value="{{ $opt }}" {{ ($row['implementation_status'] ?? 'Not started') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][achievements]" placeholder="What was delivered?">{{ $row['achievements'] ?? '' }}</textarea></td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][challenges]" placeholder="Risks / blockers">{{ $row['challenges'] ?? '' }}</textarea></td>
                                                                <td><textarea class="form-control form-control-sm" rows="2" name="content[planned][{{ $idx }}][next_steps]" placeholder="Next month">{{ $row['next_steps'] ?? '' }}</textarea></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @elseif($monthlyYearPlan && $monthlyHasSubDepartments)
                                            <p class="text-muted mb-4">No year plan lines are marked for <strong>{{ $reportMonthName }}</strong> in your sub-departments. You can still summarize the month and add off-plan work.</p>
                                        @endif

                                        <h6 class="text-primary mb-2">Month summary</h6>
                                        <div class="mb-4">
                                            <textarea class="form-control @error('content.month_summary') is-invalid @enderror" name="content[month_summary]" rows="4" placeholder="Overall narrative for the month (highlights, coordination, learning)">{{ old('content.month_summary', $mContent['month_summary'] ?? '') }}</textarea>
                                            @error('content.month_summary')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                            <h6 class="text-primary mb-0">Work outside the year plan</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" id="addMonthlyOffPlanRow">
                                                <i class="ti ti-plus me-1"></i> Add row
                                            </button>
                                        </div>
                                        <p class="small text-muted">Report initiatives not captured as year plan lines for this month.</p>
                                        <div class="table-responsive mb-2">
                                            <table class="table table-bordered align-middle" id="monthlyOffPlanTable">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Title</th>
                                                        <th>Description</th>
                                                        <th style="min-width: 120px;">Status</th>
                                                        <th>Notes</th>
                                                        <th style="width: 48px;"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="monthlyOffPlanBody">
                                                    @foreach($mOffPlanOld as $oi => $orow)
                                                        @php $orow = is_array($orow) ? $orow : []; @endphp
                                                        <tr>
                                                            <td><input type="text" class="form-control form-control-sm" name="content[off_plan][{{ $oi }}][title]" value="{{ $orow['title'] ?? '' }}" placeholder="Short title"></td>
                                                            <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][{{ $oi }}][description]" placeholder="What was done?">{{ $orow['description'] ?? '' }}</textarea></td>
                                                            <td><input type="text" class="form-control form-control-sm" name="content[off_plan][{{ $oi }}][status]" value="{{ $orow['status'] ?? '' }}" placeholder="e.g. Done"></td>
                                                            <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][{{ $oi }}][notes]" placeholder="Optional">{{ $orow['notes'] ?? '' }}</textarea></td>
                                                            <td class="text-center align-middle">
                                                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-off-plan-row" title="Remove">&times;</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @error('content.planned')
                                            <div class="text-danger small mb-2">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <!-- Optional PDF + attachment (monthly_report) -->
                            <div class="col-md-6 mb-3" id="fileUploadContainer"
                                @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="file" class="form-label">
                                        Optional PDF summary
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('file') is-invalid @enderror"
                                                name="file"
                                                id="file"
                                                accept=".pdf">
                                            <label class="custom-file-label" for="file" id="fileLabel">
                                                Choose PDF (optional)…
                                            </label>
                                        </div>
                                    </div>
                                    @error('file')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">Optional. Max 10MB.</small>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3" id="monthlyAttachmentContainer" @if(!isset($selectedType) || $selectedType !== 'monthly_report') style="display: none;" @endif>
                                <div class="form-group">
                                    <label for="attachment" class="form-label">
                                        Optional supporting file
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('attachment') is-invalid @enderror"
                                                name="attachment"
                                                id="attachment"
                                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.xls">
                                            <label class="custom-file-label" for="attachment" id="attachmentLabel">
                                                Attachment (optional)…
                                            </label>
                                        </div>
                                    </div>
                                    @error('attachment')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">PDF, images, Word, Excel. Max 10MB.</small>
                                </div>
                            </div>
                            @endif

                            <!-- Online Weekly Plan Template -->
                            @if(isset($selectedType) && $selectedType === 'weekly_plan')
                                <div class="col-12 mb-3">
                                    <div class="card border-primary">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-target me-2"></i>1. Key Objective for the Week</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="main_target" class="form-label">
                                                    Objective / Main Target <span class="text-danger">*</span>
                                                </label>
                                                <textarea class="form-control @error('main_target') is-invalid @enderror"
                                                    name="main_target"
                                                    id="main_target"
                                                    rows="3"
                                                    required
                                                    placeholder="Enter the main objective for this week">{{ old('main_target') }}</textarea>
                                                @error('main_target')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">
                                                    This becomes the “Main Target” used by weekly reports.
                                                </small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="content[objective][description]" rows="3"
                                                        placeholder="Short description of the objective">{{ old('content.objective.description') }}</textarea>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="content[objective][status]">
                                                        <option value="" {{ old('content.objective.status') ? '' : 'selected' }}>-- Select Status --</option>
                                                        @foreach(['Not started', 'Completed', 'Carried', 'In Progress'] as $opt)
                                                            <option value="{{ $opt }}" {{ old('content.objective.status') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Reflection / Progress</label>
                                                    <textarea class="form-control" name="content[objective][reflection_progress]" rows="3"
                                                        placeholder="What progress/reflection did you have?">{{ old('content.objective.reflection_progress') }}</textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-calendar me-2"></i>2. Planned Activities</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addActivityRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Activity
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 130px;">Day</th>
                                                            <th>Activity</th>
                                                            <th style="width: 130px;">Location</th>
                                                            <th style="width: 220px;">Staff/Team</th>
                                                            <th>Notes / Inclusion</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="plannedActivitiesBody">
                                                        @php
                                                            $dayOptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                                            $activityRows = old('content.planned_activities', [
                                                                ['day' => 'Monday', 'activity' => '', 'location' => '', 'team_member_ids' => [], 'notes_inclusion' => ''],
                                                            ]);
                                                            if (!is_array($activityRows) || empty($activityRows)) {
                                                                $activityRows = [['day' => 'Monday', 'activity' => '', 'location' => '', 'team_member_ids' => [], 'notes_inclusion' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($activityRows as $i => $activityRow)
                                                            @php $activityRow = is_array($activityRow) ? $activityRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <select class="form-select" name="content[planned_activities][{{ $i }}][day]">
                                                                        <option value="">-- Select --</option>
                                                                        @foreach($dayOptions as $dayOpt)
                                                                            <option value="{{ $dayOpt }}" {{ old("content.planned_activities.$i.day", $activityRow['day'] ?? '') === $dayOpt ? 'selected' : '' }}>{{ $dayOpt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[planned_activities][{{ $i }}][activity]"
                                                                        placeholder="Activity">{{ old("content.planned_activities.$i.activity", $activityRow['activity'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[planned_activities][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.planned_activities.$i.location", $activityRow['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $selectedTeamIds = old("content.planned_activities.$i.team_member_ids", $activityRow['team_member_ids'] ?? []);
                                                                        if (!is_array($selectedTeamIds)) $selectedTeamIds = [];
                                                                    @endphp
                                                                    <select class="form-select" multiple
                                                                        name="content[planned_activities][{{ $i }}][team_member_ids][]">
                                                                        @foreach(($teamUsers ?? collect()) as $teamUser)
                                                                            <option value="{{ $teamUser->id }}" {{ in_array((string)$teamUser->id, array_map('strval', $selectedTeamIds), true) ? 'selected' : '' }}>
                                                                                {{ $teamUser->name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[planned_activities][{{ $i }}][notes_inclusion]"
                                                                        placeholder="Notes">{{ old("content.planned_activities.$i.notes_inclusion", $activityRow['notes_inclusion'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-activity-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-info">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-users me-2"></i>3. Key Meetings / Engagements</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addMeetingRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Meeting
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 140px;">Date</th>
                                                            <th>Meeting / Engagement</th>
                                                            <th style="width: 200px;">Participant(s)</th>
                                                            <th style="width: 180px;">Location</th>
                                                            <th>Reactions / Notes</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="keyMeetingsBody">
                                                        @php
                                                            $meetingRows = old('content.key_meetings', [
                                                                ['date' => '', 'meeting' => '', 'participants' => '', 'location' => '', 'reactions_notes' => ''],
                                                            ]);
                                                            if (!is_array($meetingRows) || empty($meetingRows)) {
                                                                $meetingRows = [['date' => '', 'meeting' => '', 'participants' => '', 'location' => '', 'reactions_notes' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($meetingRows as $i => $meetingRow)
                                                            @php $meetingRow = is_array($meetingRow) ? $meetingRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][date]"
                                                                        value="{{ old("content.key_meetings.$i.date", $meetingRow['date'] ?? '') }}">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][meeting]"
                                                                        value="{{ old("content.key_meetings.$i.meeting", $meetingRow['meeting'] ?? '') }}"
                                                                        placeholder="e.g., MIT Engagement - Dr. Nibu">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][participants]"
                                                                        value="{{ old("content.key_meetings.$i.participants", $meetingRow['participants'] ?? '') }}"
                                                                        placeholder="e.g., Dr. Theo / DUCE / Name of institution">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[key_meetings][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.key_meetings.$i.location", $meetingRow['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[key_meetings][{{ $i }}][reactions_notes]"
                                                                        placeholder="What happened?">{{ old("content.key_meetings.$i.reactions_notes", $meetingRow['reactions_notes'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-meeting-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-success">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>4. Budget</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addBudgetRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Budget
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Budget Item / Activity</th>
                                                            <th style="width: 300px;">Description / Purpose</th>
                                                            <th style="width: 180px;">Amount (TZS)</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="budgetBody">
                                                        @php
                                                            $budgetRows = old('content.budget', [
                                                                ['item' => '', 'description_purpose' => '', 'amount' => ''],
                                                            ]);
                                                            if (!is_array($budgetRows) || empty($budgetRows)) {
                                                                $budgetRows = [['item' => '', 'description_purpose' => '', 'amount' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($budgetRows as $i => $budgetRow)
                                                            @php $budgetRow = is_array($budgetRow) ? $budgetRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][item]"
                                                                        value="{{ old("content.budget.$i.item", $budgetRow['item'] ?? '') }}"
                                                                        placeholder="e.g., Email account setup - Namecheap">
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[budget][{{ $i }}][description_purpose]"
                                                                        placeholder="Purpose">{{ old("content.budget.$i.description_purpose", $budgetRow['description_purpose'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][amount]"
                                                                        value="{{ old("content.budget.$i.amount", $budgetRow['amount'] ?? '') }}"
                                                                        placeholder="e.g., 25">
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-budget-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                <small class="form-text text-muted mt-2">
                                                    Amount is optional. If provided, it will be shown on the weekly report budget section.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Online Weekly Report Template -->
                            @if(isset($selectedType) && $selectedType === 'weekly_report')
                                <div class="col-12 mb-3">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0"><i class="ti ti-target me-2"></i>Main Target Accomplishment</h6>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $planMainTarget = null;
                                                $planActivities = [];
                                                if (isset($relatedPlan) && $relatedPlan && $relatedPlan->main_target) {
                                                    $planMainTarget = $relatedPlan->main_target;
                                                } elseif (old('period_id')) {
                                                    $selectedPeriod = \App\Models\WorkPeriod::find(old('period_id'));
                                                    if ($selectedPeriod) {
                                                        $plan = \App\Models\Document::where('user_id', auth()->id())
                                                            ->where('period_id', $selectedPeriod->id)
                                                            ->where('type', 'weekly_plan')
                                                            ->first();
                                                        if ($plan) {
                                                            $planMainTarget = $plan->main_target;
                                                        }
                                                    }
                                                }
                                                if (isset($relatedPlan) && $relatedPlan && is_array($relatedPlan->content ?? null)) {
                                                    $planActivities = collect($relatedPlan->content['planned_activities'] ?? [])
                                                        ->filter(fn($row) => is_array($row) && !empty(trim((string)($row['activity'] ?? ''))))
                                                        ->map(function($row) {
                                                            $day = trim((string)($row['day'] ?? ''));
                                                            $activity = trim((string)($row['activity'] ?? ''));
                                                            return [
                                                                'day' => $day,
                                                                'activity' => $activity,
                                                                'label' => $day !== '' ? ($day . ' - ' . $activity) : $activity,
                                                            ];
                                                        })
                                                        ->values()
                                                        ->all();
                                                }
                                            @endphp

                                            <div id="planTargetContainer">
                                                @if($planMainTarget)
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                                        <div class="alert alert-light border">
                                                            <p class="mb-0"><strong id="planTargetText">{{ $planMainTarget }}</strong></p>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="mb-3">
                                                        <div class="alert alert-warning" id="noPlanWarning">
                                                            <i class="ti ti-alert-circle me-2"></i>
                                                            <span id="noPlanMessage">No weekly plan found for the selected work period. Please ensure you have created a weekly plan first.</span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="mb-3">
                                                <label for="target_accomplished" class="form-label">
                                                    Was the target accomplished? <span class="text-danger">*</span>
                                                </label>
                                                <select class="form-control @error('target_accomplished') is-invalid @enderror"
                                                    name="target_accomplished"
                                                    id="target_accomplished"
                                                    required>
                                                    <option value="">-- Select Status --</option>
                                                    <option value="1" {{ old('target_accomplished') === '1' ? 'selected' : '' }}>Yes, Completed</option>
                                                    <option value="0" {{ old('target_accomplished') === '0' ? 'selected' : '' }}>No, Not Completed</option>
                                                </select>
                                                @error('target_accomplished')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                            </div>

                                            <div class="mb-0" id="targetExplanationContainer" style="display: none;">
                                                <label for="target_explanation" class="form-label">
                                                    Why was the target not accomplished? <span class="text-danger">*</span>
                                                </label>
                                                <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                                    name="target_explanation"
                                                    id="target_explanation"
                                                    rows="3"
                                                    placeholder="Please explain why the target was not accomplished...">{{ old('target_explanation') }}</textarea>
                                                @error('target_explanation')
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                <small class="form-text text-muted">
                                                    Please provide a clear explanation for why the target was not accomplished.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-book-open me-2"></i>1. Summary of the Week</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control @error('content.summary') is-invalid @enderror"
                                                name="content[summary]"
                                                rows="4"
                                                placeholder="Describe what this week focused on (and the overall progress)">{{ old('content.summary') }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-list me-2"></i>2. Weekly Activity Progress</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addReportActivityRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Progress
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 70px;">No.</th>
                                                            <th>Activity (from Weekly Plan)</th>
                                                            <th style="width: 160px;">Progress</th>
                                                            <th>Remarks/Challenges</th>
                                                            <th style="width: 180px;">Next Step</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="reportActivityProgressBody">
                                                        @php
                                                            $activityProgressRows = old('content.activity_progress', [
                                                                ['activity_output' => '', 'progress' => '', 'remarks_challenges' => '', 'next_step' => ''],
                                                            ]);
                                                            if (!is_array($activityProgressRows) || empty($activityProgressRows)) {
                                                                $activityProgressRows = [['activity_output' => '', 'progress' => '', 'remarks_challenges' => '', 'next_step' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($activityProgressRows as $i => $row)
                                                            @php $row = is_array($row) ? $row : []; @endphp
                                                            <tr>
                                                                <td class="text-center align-middle js-report-activity-row-no">{{ $i+1 }}</td>
                                                                <td>
                                                                    @php $selectedActivityOutput = old("content.activity_progress.$i.activity_output", $row['activity_output'] ?? ''); @endphp
                                                                    <select class="form-select"
                                                                        name="content[activity_progress][{{ $i }}][activity_output]"
                                                                    >
                                                                        <option value="">-- Select Activity --</option>
                                                                        @foreach($planActivities as $planActivity)
                                                                            <option value="{{ $planActivity['label'] }}" {{ $selectedActivityOutput === $planActivity['label'] ? 'selected' : '' }}>
                                                                                {{ $planActivity['label'] }}
                                                                            </option>
                                                                        @endforeach
                                                                        @if($selectedActivityOutput !== '' && !collect($planActivities)->contains(fn($a) => ($a['label'] ?? '') === $selectedActivityOutput))
                                                                            <option value="{{ $selectedActivityOutput }}" selected>{{ $selectedActivityOutput }}</option>
                                                                        @endif
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[activity_progress][{{ $i }}][progress]">
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Not started','In progress','Done','Partially done'] as $opt)
                                                                            <option value="{{ $opt }}" {{ old("content.activity_progress.$i.progress", $row['progress'] ?? '') === $opt ? 'selected' : '' }}>
                                                                                {{ $opt }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[activity_progress][{{ $i }}][remarks_challenges]"
                                                                        placeholder="Remarks/Challenges">{{ old("content.activity_progress.$i.remarks_challenges", $row['remarks_challenges'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[activity_progress][{{ $i }}][next_step]"
                                                                        placeholder="Next step">{{ old("content.activity_progress.$i.next_step", $row['next_step'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-report-activity-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-check-square me-2"></i>3. Key Achievements</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addAchievementRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Achievement
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th style="width: 70px;">No.</th>
                                                            <th>Achievement</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="achievementsBody">
                                                        @php
                                                            $achievementRows = old('content.key_achievements', ['']);
                                                            if (!is_array($achievementRows) || empty($achievementRows)) {
                                                                $achievementRows = [''];
                                                            }
                                                        @endphp
                                                        @foreach($achievementRows as $i => $achievement)
                                                            <tr>
                                                                <td class="text-center align-middle js-achievement-row-no">{{ $i + 1 }}</td>
                                                                <td>
                                                                    <textarea class="form-control @error("content.key_achievements.$i") is-invalid @enderror"
                                                                        name="content[key_achievements][{{ $i }}]"
                                                                        rows="2"
                                                                        placeholder="Achievement">{{ old("content.key_achievements.$i", is_string($achievement) ? $achievement : '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-achievement-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-alert-triangle me-2"></i>4. Challenges & Mitigation</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addChallengeRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Challenge
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Challenge(s)</th>
                                                            <th style="width: 320px;">Action Taken/Planned</th>
                                                            <th style="width: 60px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="challengesMitigationBody">
                                                        @php
                                                            $challengeRows = old('content.challenges_mitigation', [
                                                                ['challenge' => '', 'action_taken_planned' => ''],
                                                            ]);
                                                            if (!is_array($challengeRows) || empty($challengeRows)) {
                                                                $challengeRows = [['challenge' => '', 'action_taken_planned' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($challengeRows as $i => $chRow)
                                                            @php $chRow = is_array($chRow) ? $chRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[challenges_mitigation][{{ $i }}][challenge]"
                                                                        placeholder="Challenge">{{ old("content.challenges_mitigation.$i.challenge", $chRow['challenge'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[challenges_mitigation][{{ $i }}][action_taken_planned]"
                                                                        placeholder="Action taken/planned">{{ old("content.challenges_mitigation.$i.action_taken_planned", $chRow['action_taken_planned'] ?? '') }}</textarea>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-challenge-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 mb-3" id="budgetStatusSectionContainer" style="display: none;">
                                    <div class="card border-success">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>5. Budget (from weekly plan)</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="budgetStatusContainer"></div>
                                            <small class="form-text text-muted mt-2">
                                                Budget statuses are based on the budget items in your weekly plan for this period.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-light border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('documents.index') }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <div class="btn-group">
                                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" id="saveDraftBtn">
                                    <i class="ti ti-save me-1"></i> Save Draft
                                </button>
                                <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="ti ti-send me-1"></i> Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script type="application/json" id="document-form-data">
        {
            "workPeriods": {!! json_encode($workPeriods ?? []) !!},
            "defaultPeriodId": {!! json_encode($defaultPeriodId ?? null) !!},
            "selectedType": {!! json_encode($selectedType ?? null) !!},
            "currentPeriodId": {!! json_encode(isset($currentPeriod) ? $currentPeriod->id : null) !!},
            "nextPeriodId": {!! json_encode(isset($nextPeriod) ? $nextPeriod->id : null) !!},
            "relatedPlanMainTarget": {!! json_encode(isset($relatedPlan) && $relatedPlan ? $relatedPlan->main_target : null) !!}
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('createDocumentForm');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');
            const fileUploadContainer = document.getElementById('fileUploadContainer');
            const attachmentInput = document.getElementById('attachment');
            const attachmentLabel = document.getElementById('attachmentLabel');
            const typeSelect = document.getElementById('type');
            const workPeriodContainer = document.getElementById('workPeriodContainer');
            const monthYearContainer = document.getElementById('monthYearContainer');
            const yearContainer = document.getElementById('yearContainer');
            const periodSelect = document.getElementById('period_id');
            const monthSelect = document.getElementById('month');
            const yearSelect = document.getElementById('year');
            const monthlyAttachmentContainer = document.getElementById('monthlyAttachmentContainer');
            
            // Work periods data for default selection
            const formData = JSON.parse(document.getElementById('document-form-data').textContent);
            const workPeriodsData = formData.workPeriods;
            const defaultPeriodId = formData.defaultPeriodId;
            const selectedType = formData.selectedType;
            const currentPeriodId = formData.currentPeriodId;
            const nextPeriodId = formData.nextPeriodId;
            const relatedPlanMainTarget = formData.relatedPlanMainTarget;

            // Show/hide work period dropdown based on document type
            function toggleWorkPeriod() {
                const selectedTypeValue = typeSelect.value || selectedType;
                const weeklyTypes = ['weekly_plan', 'weekly_report'];
                
                if (weeklyTypes.includes(selectedTypeValue)) {
                    workPeriodContainer.style.display = '';
                    periodSelect.required = true;
                    
                    // Hide month/year fields
                    if (monthYearContainer) monthYearContainer.style.display = 'none';
                    if (yearContainer) yearContainer.style.display = 'none';
                    if (monthSelect) monthSelect.required = false;
                    if (yearSelect) yearSelect.required = false;
                    if (attachmentInput) attachmentInput.required = false;
                    if (fileUploadContainer) fileUploadContainer.style.display = 'none';
                    if (monthlyAttachmentContainer) monthlyAttachmentContainer.style.display = 'none';
                    if (fileInput) fileInput.required = false;
                    
                    // Set default period based on type if not already selected
                    if (!periodSelect.value) {
                        if (selectedTypeValue === 'weekly_plan' && nextPeriodId) {
                            periodSelect.value = nextPeriodId;
                        } else if (selectedTypeValue === 'weekly_report' && currentPeriodId) {
                            periodSelect.value = currentPeriodId;
                        } else if (defaultPeriodId) {
                            periodSelect.value = defaultPeriodId;
                        }
                    }
                } else if (selectedTypeValue === 'monthly_report') {
                    // Hide work period, show month/year
                    workPeriodContainer.style.display = 'none';
                    periodSelect.required = false;
                    periodSelect.value = '';
                    
                    // Show month/year fields
                    if (monthYearContainer) monthYearContainer.style.display = '';
                    if (yearContainer) yearContainer.style.display = '';
                    if (monthSelect) monthSelect.required = true;
                    if (yearSelect) yearSelect.required = true;
                    if (attachmentInput) attachmentInput.required = false;
                    if (fileUploadContainer) fileUploadContainer.style.display = '';
                    if (monthlyAttachmentContainer) monthlyAttachmentContainer.style.display = '';
                    if (fileInput) fileInput.required = false;
                } else {
                    // Hide both
                    workPeriodContainer.style.display = 'none';
                    periodSelect.required = false;
                    periodSelect.value = '';
                    
                    if (monthYearContainer) monthYearContainer.style.display = 'none';
                    if (yearContainer) yearContainer.style.display = 'none';
                    if (monthSelect) monthSelect.required = false;
                    if (yearSelect) yearSelect.required = false;
                    if (attachmentInput) attachmentInput.required = false;
                    if (fileUploadContainer) fileUploadContainer.style.display = 'none';
                    if (monthlyAttachmentContainer) monthlyAttachmentContainer.style.display = 'none';
                    if (fileInput) fileInput.required = false;
                }
            }

            const monthlyCreateUrl = @json(route('documents.create'));
            if (selectedType === 'monthly_report') {
                document.querySelectorAll('.js-monthly-report-reload').forEach(function (el) {
                    el.addEventListener('change', function () {
                        const m = document.getElementById('month');
                        const y = document.getElementById('year');
                        if (!m || !y || !m.value || !y.value) return;
                        const u = new URL(monthlyCreateUrl, window.location.origin);
                        u.searchParams.set('type', 'monthly_report');
                        u.searchParams.set('month', m.value);
                        u.searchParams.set('year', y.value);
                        window.location.href = u.toString();
                    });
                });
            }

            const monthlyOffPlanBody = document.getElementById('monthlyOffPlanBody');
            const addMonthlyOffPlanRow = document.getElementById('addMonthlyOffPlanRow');
            if (addMonthlyOffPlanRow && monthlyOffPlanBody) {
                addMonthlyOffPlanRow.addEventListener('click', function () {
                    const idx = monthlyOffPlanBody.querySelectorAll('tr').length;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><input type="text" class="form-control form-control-sm" name="content[off_plan][${idx}][title]" placeholder="Short title"></td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][${idx}][description]" placeholder="What was done?"></textarea></td>
                        <td><input type="text" class="form-control form-control-sm" name="content[off_plan][${idx}][status]" placeholder="e.g. Done"></td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="content[off_plan][${idx}][notes]" placeholder="Optional"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger js-remove-off-plan-row" title="Remove">&times;</button></td>
                    `;
                    monthlyOffPlanBody.appendChild(tr);
                });
                monthlyOffPlanBody.addEventListener('click', function (e) {
                    const btn = e.target.closest('.js-remove-off-plan-row');
                    if (!btn) return;
                    const rows = monthlyOffPlanBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr').remove();
                });
            }

            // Handle target accomplishment dropdown for weekly reports
            const targetAccomplishedSelect = document.getElementById('target_accomplished');
            const targetExplanationContainer = document.getElementById('targetExplanationContainer');
            const targetExplanationTextarea = document.getElementById('target_explanation');
            const addActivityRowBtn = document.getElementById('addActivityRowBtn');
            const plannedActivitiesBody = document.getElementById('plannedActivitiesBody');
            const addMeetingRowBtn = document.getElementById('addMeetingRowBtn');
            const keyMeetingsBody = document.getElementById('keyMeetingsBody');
            const addBudgetRowBtn = document.getElementById('addBudgetRowBtn');
            const budgetBody = document.getElementById('budgetBody');
            const addReportActivityRowBtn = document.getElementById('addReportActivityRowBtn');
            const reportActivityProgressBody = document.getElementById('reportActivityProgressBody');
            const addAchievementRowBtn = document.getElementById('addAchievementRowBtn');
            const achievementsBody = document.getElementById('achievementsBody');
            const addChallengeRowBtn = document.getElementById('addChallengeRowBtn');
            const challengesMitigationBody = document.getElementById('challengesMitigationBody');
            const reportActivityOptionsSource = reportActivityProgressBody
                ? reportActivityProgressBody.querySelector('select[name^="content[activity_progress]"][name$="[activity_output]"]')
                : null;
            window.__reportActivityOptionsHtml = reportActivityOptionsSource ? reportActivityOptionsSource.innerHTML : '<option value="">-- Select Activity --</option>';
            if (!window.__teamUsersOptionsHtml) {
                const teamSelect = document.querySelector('select[name^="content[planned_activities]"][name$="[team_member_ids][]"]');
                window.__teamUsersOptionsHtml = teamSelect ? teamSelect.innerHTML : '';
            }

            function updateReportActivityRowNumbers() {
                if (!reportActivityProgressBody) return;
                reportActivityProgressBody.querySelectorAll('.js-report-activity-row-no').forEach((cell, idx) => {
                    cell.textContent = String(idx + 1);
                });
            }
            function updateAchievementRowNumbers() {
                if (!achievementsBody) return;
                achievementsBody.querySelectorAll('.js-achievement-row-no').forEach((cell, idx) => {
                    cell.textContent = String(idx + 1);
                });
            }

            function toggleTargetExplanation() {
                if (targetAccomplishedSelect && targetExplanationContainer && targetExplanationTextarea) {
                    const value = targetAccomplishedSelect.value;
                    if (value === '0') {
                        // Not accomplished - show explanation and make it required
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Accomplished - hide explanation and make it not required
                        targetExplanationContainer.style.display = 'none';
                        targetExplanationTextarea.required = false;
                        targetExplanationTextarea.value = '';
                    }
                }
            }

            if (targetAccomplishedSelect) {
                targetAccomplishedSelect.addEventListener('change', toggleTargetExplanation);
                // Check initial state
                toggleTargetExplanation();
            }

            if (addActivityRowBtn && plannedActivitiesBody) {
                let activityIndex = plannedActivitiesBody.querySelectorAll('tr').length;

                addActivityRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <select class="form-select" name="content[planned_activities][${activityIndex}][day]">
                                <option value="">-- Select --</option>
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                                <option value="Saturday">Saturday</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="2" name="content[planned_activities][${activityIndex}][activity]" placeholder="Activity"></textarea></td>
                        <td>
                            <select class="form-select" name="content[planned_activities][${activityIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td>
                            <select class="form-select" multiple name="content[planned_activities][${activityIndex}][team_member_ids][]">
                                ${(window.__teamUsersOptionsHtml || '')}
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="2" name="content[planned_activities][${activityIndex}][notes_inclusion]" placeholder="Notes"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-activity-row">&times;</button></td>
                    `;
                    plannedActivitiesBody.appendChild(row);
                    activityIndex++;
                });

                plannedActivitiesBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-activity-row');
                    if (!btn) return;

                    const rows = plannedActivitiesBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addMeetingRowBtn && keyMeetingsBody) {
                let meetingIndex = keyMeetingsBody.querySelectorAll('tr').length;

                addMeetingRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="date" class="form-control" name="content[key_meetings][${meetingIndex}][date]"></td>
                        <td><input class="form-control" name="content[key_meetings][${meetingIndex}][meeting]" placeholder="Meeting / engagement"></td>
                        <td><input class="form-control" name="content[key_meetings][${meetingIndex}][participants]" placeholder="e.g., Dr. Theo / DUCE"></td>
                        <td>
                            <select class="form-select" name="content[key_meetings][${meetingIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="2" name="content[key_meetings][${meetingIndex}][reactions_notes]" placeholder="What happened?"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-meeting-row">&times;</button></td>
                    `;
                    keyMeetingsBody.appendChild(row);
                    meetingIndex++;
                });

                keyMeetingsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-meeting-row');
                    if (!btn) return;

                    const rows = keyMeetingsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addBudgetRowBtn && budgetBody) {
                let budgetIndex = budgetBody.querySelectorAll('tr').length;

                addBudgetRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input class="form-control" name="content[budget][${budgetIndex}][item]" placeholder="Budget item / activity"></td>
                        <td><textarea class="form-control" rows="2" name="content[budget][${budgetIndex}][description_purpose]" placeholder="Description / purpose"></textarea></td>
                        <td><input class="form-control" name="content[budget][${budgetIndex}][amount]" placeholder="Amount"></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-budget-row">&times;</button></td>
                    `;
                    budgetBody.appendChild(row);
                    budgetIndex++;
                });

                budgetBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-budget-row');
                    if (!btn) return;

                    const rows = budgetBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addReportActivityRowBtn && reportActivityProgressBody) {
                let reportActivityIndex = reportActivityProgressBody.querySelectorAll('tr').length;

                addReportActivityRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-report-activity-row-no"></td>
                        <td>
                            <select class="form-select" name="content[activity_progress][${reportActivityIndex}][activity_output]">
                                ${window.__reportActivityOptionsHtml || '<option value="">-- Select Activity --</option>'}
                            </select>
                        </td>
                        <td>
                            <select class="form-select" name="content[activity_progress][${reportActivityIndex}][progress]">
                                <option value="">-- Select --</option>
                                <option value="Not started">Not started</option>
                                <option value="In progress">In progress</option>
                                <option value="Done">Done</option>
                                <option value="Partially done">Partially done</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="2" name="content[activity_progress][${reportActivityIndex}][remarks_challenges]" placeholder="Remarks/Challenges"></textarea></td>
                        <td><textarea class="form-control" rows="2" name="content[activity_progress][${reportActivityIndex}][next_step]" placeholder="Next step"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-report-activity-row">&times;</button></td>
                    `;
                    reportActivityProgressBody.appendChild(row);
                    reportActivityIndex++;
                    updateReportActivityRowNumbers();
                });

                reportActivityProgressBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-report-activity-row');
                    if (!btn) return;
                    const rows = reportActivityProgressBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                    updateReportActivityRowNumbers();
                });

                updateReportActivityRowNumbers();
            }

            if (addAchievementRowBtn && achievementsBody) {
                let achievementIndex = achievementsBody.querySelectorAll('tr').length;
                addAchievementRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-achievement-row-no"></td>
                        <td><textarea class="form-control" name="content[key_achievements][${achievementIndex}]" rows="2" placeholder="Achievement"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-achievement-row">&times;</button></td>
                    `;
                    achievementsBody.appendChild(row);
                    achievementIndex++;
                    updateAchievementRowNumbers();
                });
                achievementsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-achievement-row');
                    if (!btn) return;
                    const rows = achievementsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                    updateAchievementRowNumbers();
                });
                updateAchievementRowNumbers();
            }

            if (addChallengeRowBtn && challengesMitigationBody) {
                let challengeIndex = challengesMitigationBody.querySelectorAll('tr').length;
                addChallengeRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><textarea class="form-control" rows="2" name="content[challenges_mitigation][${challengeIndex}][challenge]" placeholder="Challenge"></textarea></td>
                        <td><textarea class="form-control" rows="2" name="content[challenges_mitigation][${challengeIndex}][action_taken_planned]" placeholder="Action taken/planned"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-challenge-row">&times;</button></td>
                    `;
                    challengesMitigationBody.appendChild(row);
                    challengeIndex++;
                });
                challengesMitigationBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-challenge-row');
                    if (!btn) return;
                    const rows = challengesMitigationBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            // Load plan's main target when period changes (for weekly reports)
            if (selectedType === 'weekly_report' && periodSelect) {
                const budgetStatusSectionContainer = document.getElementById('budgetStatusSectionContainer');
                const budgetStatusContainer = document.getElementById('budgetStatusContainer');

                function loadPlanData(periodId) {
                    fetch(`/documents/plan-target?period_id=${periodId}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`Failed to load plan target (${response.status})`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const planTargetContainer = document.getElementById('planTargetContainer');
                        if (planTargetContainer) {
                            if (data.plan_exists) {
                                const mainTargetText = data.main_target ? data.main_target : 'Weekly plan exists for this period, but main target is empty.';
                                planTargetContainer.innerHTML = `
                                    <div class="mb-3">
                                        <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                        <div class="alert alert-light border">
                                            <p class="mb-0"><strong id="planTargetText">${mainTargetText}</strong></p>
                                        </div>
                                    </div>
                                `;
                            } else {
                                planTargetContainer.innerHTML = `
                                    <div class="mb-3">
                                        <div class="alert alert-warning" id="noPlanWarning">
                                            <i class="ti ti-alert-circle me-2"></i>
                                            <span id="noPlanMessage">No weekly plan found for the selected work period. Please ensure you have created a weekly plan first.</span>
                                        </div>
                                    </div>
                                `;
                            }
                        }

                        if (budgetStatusSectionContainer && budgetStatusContainer) {
                            const budgetItems = data.budget_items || [];
                            if (budgetItems.length > 0) {
                                budgetStatusSectionContainer.style.display = '';
                                budgetStatusContainer.innerHTML = budgetItems.map((item, idx) => {
                                    const amount = item.amount ?? '';
                                    const label = item.item ?? '';
                                    return `
                                        <div class="mb-3 border rounded p-2">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                <div class="me-3">
                                                    <div class="fw-semibold">${label}</div>
                                                    <small class="text-muted">Amount: ${amount}</small>
                                                </div>
                                                <div class="flex-grow-1" style="min-width: 260px;">
                                                    <label class="form-label mb-1">Budget status</label>
                                                    <select class="form-select"
                                                        name="content[budget_statuses][${idx}][status]">
                                                        ${['Not started','Pending','Received','Paid'].map(opt => `
                                                            <option value="${opt}">${opt}</option>
                                                        `).join('')}
                                                    </select>
                                                    <textarea class="form-control mt-2"
                                                        name="content[budget_statuses][${idx}][notes]"
                                                        rows="2"
                                                        placeholder="Notes (optional)"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }).join('');
                            } else {
                                budgetStatusSectionContainer.style.display = 'none';
                                budgetStatusContainer.innerHTML = '';
                            }
                        }

                        if (reportActivityProgressBody) {
                            const plannedActivities = data.planned_activities || [];
                            const optionsHtml = [
                                '<option value="">-- Select Activity --</option>',
                                ...plannedActivities.map(item => `<option value="${item.label}">${item.label}</option>`),
                            ].join('');
                            window.__reportActivityOptionsHtml = optionsHtml;
                            reportActivityProgressBody.querySelectorAll('select[name^="content[activity_progress]"][name$="[activity_output]"]').forEach((select) => {
                                const currentValue = select.value;
                                select.innerHTML = optionsHtml;
                                if (currentValue) {
                                    const existing = Array.from(select.options).some(opt => opt.value === currentValue);
                                    if (!existing) {
                                        const opt = document.createElement('option');
                                        opt.value = currentValue;
                                        opt.textContent = currentValue;
                                        select.appendChild(opt);
                                    }
                                    select.value = currentValue;
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching plan data:', error);
                    });
                }

                periodSelect.addEventListener('change', function() {
                    const periodId = this.value;
                    if (periodId) {
                        loadPlanData(periodId);
                    }
                });

                // Load initial state (current selected period)
                if (periodSelect.value) {
                    loadPlanData(periodSelect.value);
                }
            }

            // Ensure action is set when buttons are clicked
            const saveDraftBtn = document.getElementById('saveDraftBtn');
            const hiddenActionInput = document.getElementById('hiddenAction');
            
            if (saveDraftBtn && hiddenActionInput) {
                saveDraftBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'draft';
                });
            }
            
            if (submitBtn && hiddenActionInput) {
                submitBtn.addEventListener('click', function() {
                    hiddenActionInput.value = 'submit';
                });
            }

            // Initialize on page load
            if (typeSelect && !typeSelect.disabled) {
                typeSelect.addEventListener('change', toggleWorkPeriod);
                toggleWorkPeriod(); // Check initial state
            } else if (selectedType) {
                // If type is pre-selected and disabled, ensure correct fields are shown
                if (['weekly_plan', 'weekly_report'].includes(selectedType)) {
                    workPeriodContainer.style.display = '';
                    periodSelect.required = true;
                } else if (selectedType === 'monthly_report') {
                    if (monthYearContainer) monthYearContainer.style.display = '';
                    if (yearContainer) yearContainer.style.display = '';
                    if (monthSelect) monthSelect.required = true;
                    if (yearSelect) yearSelect.required = true;
                    if (attachmentInput) attachmentInput.required = false;
                    if (fileUploadContainer) fileUploadContainer.style.display = '';
                    if (monthlyAttachmentContainer) monthlyAttachmentContainer.style.display = '';
                    if (fileInput) fileInput.required = false;
                }
                toggleWorkPeriod(); // Also run to ensure everything is set correctly
            }

            // Update file label when file is selected
            if (fileInput && fileLabel) {
            fileInput.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Choose PDF file...';
                fileLabel.textContent = fileName;
            });
            }

            // Update attachment label when file is selected
            if (attachmentInput && attachmentLabel) {
                attachmentInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose attachment file...';
                    attachmentLabel.textContent = fileName;
                });
            }

            form.addEventListener('submit', function(e) {
                const clickedButton = e.submitter;
                const action = clickedButton?.value || document.getElementById('hiddenAction')?.value || 'draft';
                
                // Ensure the hidden action field is set to the correct value
                const hiddenActionInput = document.getElementById('hiddenAction');
                if (hiddenActionInput) {
                    hiddenActionInput.value = action;
                }
                
                const selectedTypeValue = typeSelect.value || selectedType;
                const weeklyTypes = ['weekly_plan', 'weekly_report'];
                
                // Validate work period for weekly plans and reports
                if (weeklyTypes.includes(selectedTypeValue)) {
                    if (!periodSelect.value) {
                        e.preventDefault();
                        alert('Please select a work period.');
                        return false;
                    }
                }

                // Validate month and year for monthly reports
                if (selectedTypeValue === 'monthly_report') {
                    if (monthSelect && !monthSelect.value) {
                        e.preventDefault();
                        alert('Please select a month for the monthly report.');
                        return false;
                    }
                    if (yearSelect && !yearSelect.value) {
                        e.preventDefault();
                        alert('Please select a year for the monthly report.');
                        return false;
                    }
                }

                // Validate target accomplishment for weekly reports
                if (selectedTypeValue === 'weekly_report') {
                    const targetAccomplished = document.getElementById('target_accomplished');
                    if (targetAccomplished && !targetAccomplished.value) {
                        e.preventDefault();
                        alert('Please indicate whether the target was accomplished.');
                        return false;
                    }
                    
                    // If not accomplished, explanation is required
                    if (targetAccomplished && targetAccomplished.value === '0') {
                        const targetExplanation = document.getElementById('target_explanation');
                        if (targetExplanation && !targetExplanation.value.trim()) {
                            e.preventDefault();
                            alert('Please explain why the target was not accomplished.');
                            return false;
                        }
                    }
                }

                // Validate main target for weekly plans
                if (selectedTypeValue === 'weekly_plan') {
                    const mainTarget = document.getElementById('main_target');
                    if (mainTarget && !mainTarget.value.trim()) {
                        e.preventDefault();
                        alert('Please enter the main target for this week.');
                        return false;
                    }
                }
                
                if (selectedTypeValue === 'monthly_report' && fileInput && fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    if (file.type !== 'application/pdf') {
                        e.preventDefault();
                        alert('Please select a valid PDF file.');
                        return false;
                    }
                    if (file.size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('PDF file size must be less than 10MB.');
                        return false;
                    }
                }

                if (selectedTypeValue === 'monthly_report' && attachmentInput && attachmentInput.files && attachmentInput.files.length > 0) {
                    const att = attachmentInput.files[0];
                    if (att.size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('Attachment file size must be less than 10MB.');
                        return false;
                    }
                }
                
                if (action === 'submit') {
                    if (!confirm('Are you sure you want to submit this document? Once submitted, it cannot be edited.')) {
                        e.preventDefault();
                        return false;
                    }
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Submitting...';
                    }
                } else {
                    const saveDraftBtn = document.getElementById('saveDraftBtn');
                    if (saveDraftBtn) {
                        saveDraftBtn.disabled = true;
                        saveDraftBtn.innerHTML = '<i class="ti ti-loader spinning me-1"></i> Saving...';
                    }
                }
            });
        });
    </script>

    <style>
        .spinning {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</x-app-layout>

