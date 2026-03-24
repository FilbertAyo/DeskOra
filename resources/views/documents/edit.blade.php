<x-app-layout>
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Edit Document</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.index') }}">Documents</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}">View</a></li>
                                <li class="breadcrumb-item" aria-current="page">Edit</li>
            </ul>
        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-sm btn-info text-white">
                <i class="ti ti-eye me-1"></i> View Document
            </a>
            <a href="{{ route('documents.index') }}" class="btn btn-sm btn-light">
                <i class="ti ti-arrow-left me-1"></i> Back to Documents
            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ti ti-alert-circle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <form method="POST" action="{{ route('documents.update', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" id="editDocumentForm" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <input type="hidden" name="action" id="hiddenAction" value="draft">
                <div class="card shadow-none border">
                    <div class="card-header bg-white border-bottom">
                        <div class="row align-items-center">
                            <div class="col-md-8 mb-2 mb-md-0">
                        <h5 class="mb-0">
                            <i class="ti ti-file-text me-2"></i>Edit Document
                            <span class="badge bg-info ml-2">{{ $document->type_name }}</span>
                        </h5>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <small class="text-muted">Update fields below, then save draft or submit.</small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Document Type (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">Document Type</label>
                                    <div class="input-group">
                                       
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->type_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Document type cannot be changed</small>
                                </div>
                            </div>

                            <!-- State (Read-only) -->
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label class="form-label">State</label>
                                    <div class="input-group">
                                      
                                        <input type="text"
                                            class="form-control"
                                            value="{{ $document->state_name }}"
                                            disabled>
                                    </div>
                                    <small class="form-text text-muted">Current document state</small>
                                </div>
                            </div>

                            @if($document->type === 'monthly_report')
                            <!-- Current File -->
                            @if($document->file_path)
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <h6 class="mb-2">
                                        <i class="ti ti-file me-2"></i>Current PDF File
                                    </h6>
                                    <p class="mb-2">
                                        <strong>File:</strong> {{ $document->original_filename ?? 'Document.pdf' }}<br>
                                        <strong>Size:</strong> {{ $document->formatted_file_size }}<br>
                                        <strong>Uploaded:</strong> {{ $document->created_at->format('M d, Y H:i') }}
                                    </p>
                                    <a href="{{ $document->file_url }}" target="_blank" class="btn btn-sm btn-info">
                                        <i class="ti ti-download me-1"></i> View/Download Current File
                                    </a>
                                </div>
                            </div>
                            @endif

                            <!-- PDF File Upload (Optional - to replace existing file) -->
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="file" class="form-label">
                                        Replace PDF File <span class="text-muted">(Optional)</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('file') is-invalid @enderror"
                                                name="file"
                                                id="file"
                                                accept=".pdf">
                                            <label class="custom-file-label" for="file" id="fileLabel">
                                                Choose new PDF file...
                                            </label>
                                        </div>
                                    </div>
                                    @error('file')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="ti ti-info me-1"></i>
                                        Only PDF files are allowed. Maximum file size: 10MB. Leave empty to keep current file.
                                    </small>
                                </div>
                            </div>
                            @endif

                            <!-- Online Weekly Plan Template -->
                            @if($document->type === 'weekly_plan')
                                @php
                                    $content = is_array($document->content) ? $document->content : [];
                                    $plannedActivities = $content['planned_activities'] ?? [];
                                    $keyMeetings = $content['key_meetings'] ?? [];
                                    $budget = $content['budget'] ?? [];
                                    $objective = $content['objective'] ?? [];
                                @endphp

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
                                                    required>{{ old('main_target', $document->main_target) }}</textarea>
                                    @error('main_target')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Description</label>
                                                    <textarea class="form-control" name="content[objective][description]" rows="3"
                                                        placeholder="Short description of the objective">{{ old('content.objective.description', $objective['description'] ?? '') }}</textarea>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-select" name="content[objective][status]">
                                                        <option value="" {{ old('content.objective.status', $objective['status'] ?? '') ? '' : 'selected' }}>-- Select Status --</option>
                                                        @foreach(['Not started', 'Completed', 'Carried', 'In Progress'] as $opt)
                                                            <option value="{{ $opt }}" {{ old('content.objective.status', $objective['status'] ?? '') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Reflection / Progress</label>
                                                    <textarea class="form-control" name="content[objective][reflection_progress]" rows="3"
                                                        placeholder="What progress/reflection did you have?">{{ old('content.objective.reflection_progress', $objective['reflection_progress'] ?? '') }}</textarea>
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
                                                            $activityRows = old('content.planned_activities', $plannedActivities ?? []);
                                                            if (!is_array($activityRows) || empty($activityRows)) {
                                                                $activityRows = [['day' => 'Monday', 'activity' => '', 'location' => '', 'team_member_ids' => [], 'notes_inclusion' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($activityRows as $i => $activityRow)
                                                            @php $row = is_array($activityRow) ? $activityRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <select class="form-select" name="content[planned_activities][{{ $i }}][day]">
                                                                        <option value="">-- Select --</option>
                                                                        @foreach($dayOptions as $dayOpt)
                                                                            <option value="{{ $dayOpt }}" {{ old("content.planned_activities.$i.day", $row['day'] ?? '') === $dayOpt ? 'selected' : '' }}>{{ $dayOpt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[planned_activities][{{ $i }}][activity]"
                                                                        placeholder="Activity">{{ old("content.planned_activities.$i.activity", $row['activity'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[planned_activities][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.planned_activities.$i.location", $row['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $selectedTeamIds = old("content.planned_activities.$i.team_member_ids", $row['team_member_ids'] ?? []);
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
                                                                        placeholder="Notes / inclusion">{{ old("content.planned_activities.$i.notes_inclusion", $row['notes_inclusion'] ?? '') }}</textarea>
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
                                                            $meetingRows = old('content.key_meetings', $keyMeetings ?? []);
                                                            if (!is_array($meetingRows) || empty($meetingRows)) {
                                                                $meetingRows = [['date' => '', 'meeting' => '', 'participants' => '', 'location' => '', 'reactions_notes' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($meetingRows as $i => $meetingRow)
                                                            @php $row = is_array($meetingRow) ? $meetingRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input type="date" class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][date]"
                                                                        value="{{ old("content.key_meetings.$i.date", $row['date'] ?? '') }}">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][meeting]"
                                                                        value="{{ old("content.key_meetings.$i.meeting", $row['meeting'] ?? '') }}"
                                                                        placeholder="e.g., MIT Engagement - Dr. Nibu">
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][participants]"
                                                                        value="{{ old("content.key_meetings.$i.participants", $row['participants'] ?? '') }}"
                                                                        placeholder="e.g., Dr. Theo / DUCE / Name of institution">
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[key_meetings][{{ $i }}][location]"
                                                                    >
                                                                        <option value="">-- Select --</option>
                                                                        @foreach(['Office', 'Field', 'Virtual', 'On site'] as $loc)
                                                                            <option value="{{ $loc }}" {{ old("content.key_meetings.$i.location", $row['location'] ?? '') === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[key_meetings][{{ $i }}][reactions_notes]"
                                                                        placeholder="Notes">{{ old("content.key_meetings.$i.reactions_notes", $row['reactions_notes'] ?? '') }}</textarea>
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
                                                            $budgetRows = old('content.budget', $budget ?? []);
                                                            if (!is_array($budgetRows) || empty($budgetRows)) {
                                                                $budgetRows = [['item' => '', 'description_purpose' => '', 'amount' => '']];
                                                            }
                                                        @endphp
                                                        @foreach($budgetRows as $i => $budgetRow)
                                                            @php $row = is_array($budgetRow) ? $budgetRow : []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][item]"
                                                                        value="{{ old("content.budget.$i.item", $row['item'] ?? '') }}"
                                                                        placeholder="Budget item">
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[budget][{{ $i }}][description_purpose]"
                                                                        placeholder="Purpose">{{ old("content.budget.$i.description_purpose", $row['description_purpose'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[budget][{{ $i }}][amount]"
                                                                        value="{{ old("content.budget.$i.amount", $row['amount'] ?? '') }}"
                                                                        placeholder="Amount">
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-budget-row">&times;</button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                </div>
                            </div>
                            @endif

                            <!-- Month and Year (for monthly reports) -->
                            @if($document->type === 'monthly_report')
                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="month" class="form-label">
                                        Month
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control @error('month') is-invalid @enderror"
                                            name="month"
                                            id="month">
                                            <option value="">-- Select Month --</option>
                                            @php
                                                $months = [
                                                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                                ];
                                                $selectedMonth = old('month', $document->month ?? date('n'));
                                            @endphp
                                            @foreach($months as $num => $name)
                                                <option value="{{ $num }}" {{ $selectedMonth == $num ? 'selected' : '' }}>
                                                    {{ $name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('month')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="form-group">
                                    <label for="year" class="form-label">
                                        Year
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="ti ti-calendar"></i>
                                        </span>
                                        <select class="form-control @error('year') is-invalid @enderror"
                                            name="year"
                                            id="year">
                                            <option value="">-- Select Year --</option>
                                            @php
                                                $currentYear = date('Y');
                                                $selectedYear = old('year', $document->year ?? $currentYear);
                                            @endphp
                                            @for($y = $currentYear; $y >= $currentYear - 5; $y--)
                                                <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>
                                                    {{ $y }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>
                                    @error('year')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Current Attachment -->
                            @if($document->attachment)
                            <div class="col-12 mb-3">
                                <div class="alert alert-info">
                                    <h6 class="mb-2">
                                        <i class="ti ti-paperclip me-2"></i>Current Attachment
                                    </h6>
                                    <p class="mb-2">
                                        <strong>Attachment:</strong> <a href="{{ asset('storage/' . $document->attachment) }}" target="_blank">View Current Attachment</a>
                                    </p>
                                </div>
                            </div>
                            @endif

                            <!-- Attachment Upload (Optional - to replace existing attachment) -->
                            <div class="col-12 mb-3">
                                <div class="form-group">
                                    <label for="attachment" class="form-label">
                                        Replace Monthly Archive/Attachment <span class="text-muted">(Optional)</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file"
                                                class="custom-file-input @error('attachment') is-invalid @enderror"
                                                name="attachment"
                                                id="attachment"
                                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx,.xls">
                                            <label class="custom-file-label" for="attachment" id="attachmentLabel">
                                                Choose new attachment file...
                                            </label>
                                        </div>
                                    </div>
                                    @error('attachment')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <small class="form-text text-muted">
                                        <i class="ti ti-info me-1"></i>
                                        Accepted formats: PDF, Images, Word, Excel. Maximum file size: 10MB. Leave empty to keep current attachment.
                                    </small>
                                </div>
                            </div>
                            @endif

                            <!-- Online Weekly Report Template -->
                            @if($document->type === 'weekly_report')
                                @php
                                    $content = is_array($document->content) ? $document->content : [];
                                    $objectiveSummary = $content['summary'] ?? '';
                                    $activityProgress = $content['activity_progress'] ?? [];
                                    $keyAchievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
                                    $challengesMitigation = $content['challenges_mitigation'] ?? [];

                                            $relatedPlan = \App\Models\Document::where('user_id', $document->user_id)
                                                ->where('period_id', $document->period_id)
                                                ->where('type', 'weekly_plan')
                                                ->first();
                                    $planBudget = is_array($relatedPlan?->content ?? null) ? ($relatedPlan->content['budget'] ?? []) : [];
                                    $planActivities = is_array($relatedPlan?->content ?? null)
                                        ? collect($relatedPlan->content['planned_activities'] ?? [])
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
                                            ->all()
                                        : [];
                                    $budgetStatuses = $content['budget_statuses'] ?? [];
                                        @endphp

                                <!-- Main Target Accomplishment -->
                                <div class="col-12 mb-3">
                                    <div class="card border-info">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0"><i class="ti ti-target me-2"></i>Main Target Accomplishment</h6>
                                        </div>
                                        <div class="card-body">
                                        @if($relatedPlan && $relatedPlan->main_target)
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Main Target from Weekly Plan:</label>
                                                <div class="alert alert-light border">
                                                    <p class="mb-0"><strong>{{ $relatedPlan->main_target }}</strong></p>
                                                </div>
                                            </div>
                                        @else
                                            <div class="mb-3">
                                                <div class="alert alert-warning">
                                                    <i class="ti ti-alert-circle me-2"></i>
                                                    No weekly plan found for this work period.
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mb-3">
                                            <label for="target_accomplished" class="form-label">
                                                Was the target accomplished?
                                            </label>
                                            <select class="form-control @error('target_accomplished') is-invalid @enderror"
                                                name="target_accomplished"
                                                id="target_accomplished">
                                                <option value="">-- Select Status --</option>
                                                <option value="1" {{ old('target_accomplished', $document->target_accomplished) === true || old('target_accomplished') === '1' ? 'selected' : '' }}>Yes, Completed</option>
                                                <option value="0" {{ old('target_accomplished', $document->target_accomplished) === false || (old('target_accomplished') === '0' && $document->target_accomplished !== null) ? 'selected' : '' }}>No, Not Completed</option>
                                            </select>
                                            @error('target_accomplished')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                            @php
                                                $showTargetExplanation = (old('target_accomplished', $document->target_accomplished) === false || old('target_accomplished') === '0');
                                            @endphp
                                            <div class="mb-0" id="targetExplanationContainer" @if(!$showTargetExplanation) style="display:none;" @endif>
                                            <label for="target_explanation" class="form-label">
                                                Why was the target not accomplished?
                                            </label>
                                            <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                                name="target_explanation"
                                                id="target_explanation"
                                                rows="3"
                                                placeholder="Please explain why the target was not accomplished...">{{ old('target_explanation', $document->target_explanation) }}</textarea>
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

                                <!-- 1. Summary -->
                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-book-open me-2"></i>1. Summary of the Week</h6>
                                        </div>
                                        <div class="card-body">
                                            <textarea class="form-control"
                                                name="content[summary]"
                                                rows="4"
                                                placeholder="Describe what this week focused on">{{ old('content.summary', $objectiveSummary) }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Activity Progress -->
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
                                                            $activityProgressRows = old('content.activity_progress', $activityProgress ?? []);
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
                                                                            @php $cur = old("content.activity_progress.$i.progress", $row['progress'] ?? ''); @endphp
                                                                            <option value="{{ $opt }}" {{ $cur === $opt ? 'selected' : '' }}>{{ $opt }}</option>
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

                                <!-- 3. Key Achievements -->
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
                                                            $achievementRows = old('content.key_achievements', $keyAchievements ?? []);
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

                                <!-- 4. Challenges & Mitigation -->
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
                                                            $challengeRows = old('content.challenges_mitigation', $challengesMitigation ?? []);
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

                                <!-- 5. Budget -->
                                <div class="col-12 mb-3">
                                    <div class="card border-success">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>5. Budget (from weekly plan)</h6>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                $budgetCount = 2;
                                            @endphp
                                            <div class="table-responsive">
                                                <table class="table table-bordered align-middle mb-0">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th>Budget Item / Activity</th>
                                                            <th style="width: 200px;">Amount (TZS)</th>
                                                            <th style="width: 240px;">Status</th>
                                                            <th>Notes</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @for($i=0;$i<$budgetCount;$i++)
                                                            @php $planRow = $planBudget[$i] ?? []; @endphp
                                                            @php $statusRow = $budgetStatuses[$i] ?? []; @endphp
                                                            <tr>
                                                                <td>
                                                                    <input class="form-control" type="text" value="{{ $planRow['item'] ?? '' }}" disabled>
                                                                </td>
                                                                <td>
                                                                    <input class="form-control" type="text" value="{{ $planRow['amount'] ?? '' }}" disabled>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[budget_statuses][{{ $i }}][status]">
                                                                        @foreach(['Not started','Pending','Received','Paid'] as $opt)
                                                                            @php $curStatus = old("content.budget_statuses.$i.status", $statusRow['status'] ?? ''); @endphp
                                                                            <option value="{{ $opt }}" {{ $curStatus === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="2"
                                                                        name="content[budget_statuses][{{ $i }}][notes]">{{ old("content.budget_statuses.$i.notes", $statusRow['notes'] ?? '') }}</textarea>
                                                                </td>
                                                            </tr>
                                                        @endfor
                                                    </tbody>
                                                </table>
                                            </div>
                                            <small class="form-text text-muted mt-2">
                                                Budget statuses reflect outcomes for the budget items defined in your weekly plan.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-light border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('documents.show', \Vinkla\Hashids\Facades\Hashids::encode($document->id)) }}" class="btn btn-secondary">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editDocumentForm');
            const submitBtn = document.getElementById('submitBtn');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');
            const attachmentInput = document.getElementById('attachment');
            const attachmentLabel = document.getElementById('attachmentLabel');

            // Update file label when file is selected
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose new PDF file...';
                    fileLabel.textContent = fileName;
                });
            }

            // Update attachment label when file is selected
            if (attachmentInput && attachmentLabel) {
                attachmentInput.addEventListener('change', function(e) {
                    const fileName = e.target.files[0]?.name || 'Choose new attachment file...';
                    attachmentLabel.textContent = fileName;
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
                        // Not accomplished - show explanation
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Accomplished - hide explanation
                        targetExplanationContainer.style.display = 'none';
                        targetExplanationTextarea.required = false;
                        if (value === '1') {
                            targetExplanationTextarea.value = '';
                        }
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
                        <td><textarea class="form-control" rows="2" name="content[planned_activities][${activityIndex}][notes_inclusion]" placeholder="Notes / inclusion"></textarea></td>
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

            form.addEventListener('submit', function(e) {
                const clickedButton = e.submitter;
                const action = clickedButton?.value || document.getElementById('hiddenAction')?.value || 'draft';
                
                // Ensure the hidden action field is set to the correct value
                if (hiddenActionInput) {
                    hiddenActionInput.value = action;
                }
                
                // Validate file if provided
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        e.preventDefault();
                        alert('Please select a valid PDF file.');
                        return false;
                    }

                    // Validate file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        e.preventDefault();
                        alert('File size must be less than 10MB.');
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

