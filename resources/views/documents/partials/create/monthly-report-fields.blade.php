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
                <small class="text-muted">{{ $reportMonthName }} {{ $monthlyDefaultYear ?? '' }} - activities planned for this month in your sub-departments</small>
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
                    <table class="table table-bordered table-striped align-middle">
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
                                        <span class="fw-medium">{{ $activity->department?->name ?? '-' }}</span>
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
                                    <td><textarea class="form-control form-control-sm" rows="5" name="content[planned][{{ $idx }}][achievements]" placeholder="What was delivered?">{{ $row['achievements'] ?? '' }}</textarea></td>
                                    <td><textarea class="form-control form-control-sm" rows="5" name="content[planned][{{ $idx }}][challenges]" placeholder="Risks / blockers">{{ $row['challenges'] ?? '' }}</textarea></td>
                                    <td><textarea class="form-control form-control-sm" rows="5" name="content[planned][{{ $idx }}][next_steps]" placeholder="Next month">{{ $row['next_steps'] ?? '' }}</textarea></td>
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
                <textarea class="form-control @error('content.month_summary') is-invalid @enderror" name="content[month_summary]" rows="5" placeholder="Overall narrative for the month (highlights, coordination, learning)">{{ old('content.month_summary', $mContent['month_summary'] ?? '') }}</textarea>
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
                <table class="table table-bordered table-striped align-middle" id="monthlyOffPlanTable">
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
                                <td><textarea class="form-control form-control-sm" rows="5" name="content[off_plan][{{ $oi }}][description]" placeholder="What was done?">{{ $orow['description'] ?? '' }}</textarea></td>
                                <td><input type="text" class="form-control form-control-sm" name="content[off_plan][{{ $oi }}][status]" value="{{ $orow['status'] ?? '' }}" placeholder="e.g. Done"></td>
                                <td><textarea class="form-control form-control-sm" rows="5" name="content[off_plan][{{ $oi }}][notes]" placeholder="Optional">{{ $orow['notes'] ?? '' }}</textarea></td>
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
<div class="col-md-6 mb-3" id="fileUploadContainer">
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
                    Choose PDF (optional)...
                </label>
            </div>
        </div>
        @error('file')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">Optional. Max 10MB.</small>
    </div>
</div>

<div class="col-md-6 mb-3" id="monthlyAttachmentContainer">
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
                    Attachment (optional)...
                </label>
            </div>
        </div>
        @error('attachment')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <small class="form-text text-muted">PDF, images, Word, Excel. Max 10MB.</small>
    </div>
</div>
