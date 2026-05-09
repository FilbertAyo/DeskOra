<x-app-layout>
    @php
        $requestedBackUrl = request('return_to');
        $sessionBackUrl = session('documents.last_listing_url');
        $resolvedBackUrl = is_string($requestedBackUrl) && \Illuminate\Support\Str::startsWith($requestedBackUrl, url('/documents'))
            ? $requestedBackUrl
            : ((is_string($sessionBackUrl) && \Illuminate\Support\Str::startsWith($sessionBackUrl, url('/documents'))) ? $sessionBackUrl : route('documents.index'));
        $resolvedWeeklyPeriod = null;
        if (isset($selectedType) && in_array($selectedType, ['weekly_plan', 'weekly_report'], true) && isset($workPeriods)) {
            $resolvedWeeklyPeriod = collect($workPeriods)->firstWhere('id', old('period_id', $defaultPeriodId ?? null));
        }
        $isWeeklyPlanWindowClosed = isset($selectedType)
            && $selectedType === 'weekly_plan'
            && $resolvedWeeklyPeriod
            && $resolvedWeeklyPeriod->isPlanDeadlinePassed();
        $isWeeklyReportWindowClosed = isset($selectedType)
            && $selectedType === 'weekly_report'
            && $resolvedWeeklyPeriod
            && $resolvedWeeklyPeriod->isReportDeadlinePassed();
        $isWeeklyWindowClosed = $isWeeklyPlanWindowClosed || $isWeeklyReportWindowClosed;
    @endphp
    <div class="page-header">
        <div class="page-block">
            <div class="row align-items-center">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Create New Document</h5>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ $resolvedBackUrl }}">Documents</a></li>
                                <li class="breadcrumb-item" aria-current="page">Create</li>
                            </ul>
                        </div>
                        <div class="col-auto d-flex align-items-center gap-2">
                            <a href="{{ $resolvedBackUrl }}" class="btn btn-sm btn-light">
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
            @if($isWeeklyWindowClosed)
                <div class="alert alert-warning border mb-3" role="alert">
                    <i class="ti ti-lock me-1"></i>
                    @if($isWeeklyPlanWindowClosed)
                        Weekly plan submission window is currently closed for this period.
                    @elseif($isWeeklyReportWindowClosed)
                        Weekly report submission window is currently closed for this period.
                    @endif
                </div>
            @endif
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
                            @include('documents.partials.create.document-metadata-fields')

                            @if(isset($selectedType) && $selectedType === 'monthly_report')
                                @include('documents.partials.create.monthly-report-fields')
                            @endif

                            @if(isset($selectedType) && $selectedType === 'weekly_plan')
                                @include('documents.partials.create.weekly-plan-fields')
                            @endif

                            @if(isset($selectedType) && $selectedType === 'weekly_report')
                                @include('documents.partials.create.weekly-report-fields')
                            @endif
                        </div>
                    </div>

                    <div class="card-footer bg-light border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ $resolvedBackUrl }}" class="btn btn-secondary">
                                <i class="ti ti-x me-1"></i> Cancel
                            </a>
                            <div class="btn-group">
                                <button type="submit" name="action" value="draft" class="btn btn-outline-primary" id="saveDraftBtn" @disabled($isWeeklyWindowClosed)>
                                    <i class="ti ti-save me-1"></i> Save Draft
                                </button>
                                <button type="submit" name="action" value="submit" class="btn btn-primary" id="submitBtn" @disabled($isWeeklyWindowClosed)>
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
            "relatedPlanMainTarget": {!! json_encode(isset($relatedPlan) && $relatedPlan ? $relatedPlan->main_target : null) !!},
            "canProcessWeeklyBudgets": {!! json_encode(auth()->user()?->can('process weekly budgets') ?? false) !!}
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
            const periodHiddenInput = document.getElementById('period_id_hidden');
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
            const canProcessWeeklyBudgets = formData.canProcessWeeklyBudgets;

            // Show/hide work period dropdown based on document type
            function toggleWorkPeriod() {
                const selectedTypeValue = typeSelect.value || selectedType;
                const weeklyTypes = ['weekly_plan', 'weekly_report'];

                const syncPeriodId = () => {
                    if (periodHiddenInput) {
                        periodHiddenInput.value = periodSelect.value || '';
                    }
                };
                
                if (weeklyTypes.includes(selectedTypeValue)) {
                    workPeriodContainer.style.display = '';
                    periodSelect.required = false;
                    periodSelect.disabled = true;
                    
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
                    syncPeriodId();
                } else if (selectedTypeValue === 'monthly_report') {
                    // Hide work period, show month/year
                    workPeriodContainer.style.display = 'none';
                    periodSelect.required = false;
                    periodSelect.disabled = true;
                    periodSelect.value = '';
                    syncPeriodId();
                    
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
                    periodSelect.disabled = true;
                    periodSelect.value = '';
                    syncPeriodId();
                    
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

            const monthlyCreateUrl = "{{ route('documents.create') }}";
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
                        <td><textarea class="form-control form-control-sm" rows="5" name="content[off_plan][${idx}][description]" placeholder="What was done?"></textarea></td>
                        <td><input type="text" class="form-control form-control-sm" name="content[off_plan][${idx}][status]" placeholder="e.g. Done"></td>
                        <td><textarea class="form-control form-control-sm" rows="5" name="content[off_plan][${idx}][notes]" placeholder="Optional"></textarea></td>
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
            const targetAccomplishedSelect = document.getElementById('target_accomplished_status');
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
            const addReportMeetingRowBtn = document.getElementById('addReportMeetingRowBtn');
            const reportMeetingsBody = document.getElementById('reportMeetingsBody');
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
            function syncOffPlanInputForSelect(selectEl) {
                if (!selectEl) return;
                const cell = selectEl.closest('td');
                if (!cell) return;
                const wrap = cell.querySelector('[data-off-plan-input-wrap="true"]');
                const input = wrap ? wrap.querySelector('input[name$="[off_plan_activity]"]') : null;
                const showOffPlan = selectEl.value === '__off_plan__';
                if (!wrap || !input) return;
                wrap.style.display = showOffPlan ? '' : 'none';
                input.required = showOffPlan;
                if (!showOffPlan) {
                    input.value = '';
                }
            }

            function toggleTargetExplanation() {
                if (targetAccomplishedSelect && targetExplanationContainer && targetExplanationTextarea) {
                    const value = targetAccomplishedSelect.value;
                    if (value === 'not_completed' || value === 'partially_completed') {
                        // Not/partially accomplished - show explanation and make it required
                        targetExplanationContainer.style.display = '';
                        targetExplanationTextarea.required = true;
                    } else {
                        // Completed - hide explanation and make it not required
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
                        <td><input type="hidden" name="content[planned_activities][${activityIndex}][activity_uid]" value=""><textarea class="form-control" rows="5" name="content[planned_activities][${activityIndex}][activity]" placeholder="Activity"></textarea></td>
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
                        <td><textarea class="form-control" rows="5" name="content[planned_activities][${activityIndex}][notes_inclusion]" placeholder="Notes"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-activity-row">&times;</button></td>
                    `;
                    if (plannedActivitiesBody.firstChild) {
                        plannedActivitiesBody.insertBefore(row, plannedActivitiesBody.firstChild);
                    } else {
                        plannedActivitiesBody.appendChild(row);
                    }
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
                        <td><input type="hidden" name="content[key_meetings][${meetingIndex}][meeting_uid]" value=""><input class="form-control" name="content[key_meetings][${meetingIndex}][meeting]" placeholder="Meeting / engagement"></td>
                        <td>
                            <select class="form-select" multiple name="content[key_meetings][${meetingIndex}][participant_user_ids][]">
                                ${(window.__teamUsersOptionsHtml || '')}
                            </select>
                        </td>
                        <td>
                            <select class="form-select" name="content[key_meetings][${meetingIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="5" name="content[key_meetings][${meetingIndex}][reactions_notes]" placeholder="What happened?"></textarea></td>
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
                        <td><textarea class="form-control" rows="5" name="content[budget][${budgetIndex}][description_purpose]" placeholder="Description / purpose"></textarea></td>
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
                            <select class="form-select" name="content[activity_progress][${reportActivityIndex}][activity_output]" data-off-plan-select="true">
                                ${window.__reportActivityOptionsHtml || '<option value="">-- Select Activity --</option>'}
                            </select>
                            <div class="mt-2" data-off-plan-input-wrap="true" style="display:none;">
                                <input type="text" class="form-control" name="content[activity_progress][${reportActivityIndex}][off_plan_activity]" placeholder="Describe off-plan activity">
                            </div>
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
                        <td><textarea class="form-control" rows="5" name="content[activity_progress][${reportActivityIndex}][remarks_challenges]" placeholder="Remarks/Challenges"></textarea></td>
                        <td><textarea class="form-control" rows="5" name="content[activity_progress][${reportActivityIndex}][next_step]" placeholder="Next step"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-report-activity-row">&times;</button></td>
                    `;
                    if (reportActivityProgressBody.firstChild) {
                        reportActivityProgressBody.insertBefore(row, reportActivityProgressBody.firstChild);
                    } else {
                        reportActivityProgressBody.appendChild(row);
                    }
                    reportActivityIndex++;
                    updateReportActivityRowNumbers();
                    syncOffPlanInputForSelect(row.querySelector('[data-off-plan-select="true"]'));
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
                reportActivityProgressBody.querySelectorAll('[data-off-plan-select="true"]').forEach((selectEl) => {
                    syncOffPlanInputForSelect(selectEl);
                });
                reportActivityProgressBody.addEventListener('change', function(event) {
                    const selectEl = event.target.closest('[data-off-plan-select="true"]');
                    if (!selectEl) return;
                    syncOffPlanInputForSelect(selectEl);
                });
            }

            if (addAchievementRowBtn && achievementsBody) {
                let achievementIndex = achievementsBody.querySelectorAll('tr').length;
                addAchievementRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="text-center align-middle js-achievement-row-no"></td>
                        <td><textarea class="form-control" name="content[key_achievements][${achievementIndex}]" rows="5" placeholder="Achievement"></textarea></td>
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

            if (addReportMeetingRowBtn && reportMeetingsBody) {
                let reportMeetingIndex = reportMeetingsBody.querySelectorAll('tr').length;

                addReportMeetingRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><input type="date" class="form-control" name="content[key_meetings][${reportMeetingIndex}][date]"></td>
                        <td><input class="form-control" name="content[key_meetings][${reportMeetingIndex}][meeting]" placeholder="Meeting / engagement"></td>
                        <td>
                            <select class="form-select" multiple name="content[key_meetings][${reportMeetingIndex}][participant_user_ids][]">
                                ${(window.__teamUsersOptionsHtml || '')}
                            </select>
                        </td>
                        <td>
                            <select class="form-select" name="content[key_meetings][${reportMeetingIndex}][location]">
                                <option value="">-- Select --</option>
                                <option value="Office">Office</option>
                                <option value="Field">Field</option>
                                <option value="Virtual">Virtual</option>
                                <option value="On site">On site</option>
                            </select>
                        </td>
                        <td><textarea class="form-control" rows="5" name="content[key_meetings][${reportMeetingIndex}][reactions_notes]" placeholder="What happened?"></textarea></td>
                        <td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-report-meeting-row">&times;</button></td>
                    `;
                    reportMeetingsBody.appendChild(row);
                    reportMeetingIndex++;
                });

                reportMeetingsBody.addEventListener('click', function(event) {
                    const btn = event.target.closest('.remove-report-meeting-row');
                    if (!btn) return;
                    const rows = reportMeetingsBody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr')?.remove();
                });
            }

            if (addChallengeRowBtn && challengesMitigationBody) {
                let challengeIndex = challengesMitigationBody.querySelectorAll('tr').length;
                addChallengeRowBtn.addEventListener('click', function() {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td><textarea class="form-control" rows="5" name="content[challenges_mitigation][${challengeIndex}][challenge]" placeholder="Challenge"></textarea></td>
                        <td><textarea class="form-control" rows="5" name="content[challenges_mitigation][${challengeIndex}][action_taken_planned]" placeholder="Action taken/planned"></textarea></td>
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
                const budgetStatusTableWrapper = document.getElementById('budgetStatusTableWrapper');
                const budgetStatusTableBody = document.getElementById('budgetStatusTableBody');
                const budgetStatusEmptyAlert = document.getElementById('budgetStatusEmptyAlert');

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
                                const rowsHtml = budgetItems.map((item, idx) => {
                                    const amount = item.amount ?? '';
                                    const label = item.item ?? '';
                                    const uid = item.budget_uid ?? '';
                                    const reviewDecision = item.review_decision ?? null;
                                    let reviewBadge = '<span class="text-muted">-</span>';
                                    if (reviewDecision === 'approved') {
                                        reviewBadge = '<span class="badge bg-success">Approved</span>';
                                    } else if (reviewDecision === 'rejected') {
                                        reviewBadge = '<span class="badge bg-danger">Rejected</span>';
                                    } else if (reviewDecision === 'pending') {
                                        reviewBadge = '<span class="badge bg-warning">Pending</span>';
                                    }

                                    return `
                                        <tr>
                                            <td>
                                                <input class="form-control" type="text" value="${label}" disabled>
                                                <input type="hidden" name="content[budget_statuses][${idx}][budget_uid]" value="${uid}">
                                            </td>
                                            <td>
                                                <input class="form-control" type="text" value="${amount}" disabled>
                                            </td>
                                            <td class="text-center">${reviewBadge}</td>
                                            <td>
                                                <textarea class="form-control" name="content[budget_statuses][${idx}][notes]" rows="2" ${canProcessWeeklyBudgets ? '' : 'readonly'}></textarea>
                                            </td>
                                        </tr>
                                    `;
                                }).join('');
                                if (budgetStatusTableBody) {
                                    budgetStatusTableBody.innerHTML = rowsHtml;
                                }
                                if (budgetStatusTableWrapper) {
                                    budgetStatusTableWrapper.style.display = '';
                                }
                                if (budgetStatusEmptyAlert) {
                                    budgetStatusEmptyAlert.style.display = 'none';
                                }
                            } else {
                                if (budgetStatusTableBody) {
                                    budgetStatusTableBody.innerHTML = '';
                                }
                                if (budgetStatusTableWrapper) {
                                    budgetStatusTableWrapper.style.display = 'none';
                                }
                                if (budgetStatusEmptyAlert) {
                                    budgetStatusEmptyAlert.style.display = '';
                                }
                            }
                        }

                        if (reportActivityProgressBody) {
                            const plannedActivities = data.planned_activities || [];
                            const optionsHtml = [
                                '<option value="">-- Select Activity --</option>',
                                ...plannedActivities.map(item => `<option value="${item.label}">${item.label}</option>`),
                                '<option value="__off_plan__">Other (Not on Plan)</option>',
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
                                syncOffPlanInputForSelect(select);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching plan data:', error);
                    });
                }

                periodSelect.addEventListener('change', function() {
                    const periodId = this.value;
                    if (periodHiddenInput) {
                        periodHiddenInput.value = periodId;
                    }
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
                    periodSelect.required = false;
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
                    const targetAccomplished = document.getElementById('target_accomplished_status');
                    if (targetAccomplished && !targetAccomplished.value) {
                        e.preventDefault();
                        alert('Please select target accomplishment status.');
                        return false;
                    }
                    
                    // If not fully completed, explanation is required
                    if (targetAccomplished && (targetAccomplished.value === 'not_completed' || targetAccomplished.value === 'partially_completed')) {
                        const targetExplanation = document.getElementById('target_explanation');
                        if (targetExplanation && !targetExplanation.value.trim()) {
                            e.preventDefault();
                            alert('Please explain why the target was not fully accomplished.');
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

