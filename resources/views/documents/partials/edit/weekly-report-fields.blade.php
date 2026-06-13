                                @php
                                    $content = is_array($document->content) ? $document->content : [];
                                    $objectiveSummary = $content['summary'] ?? '';
                                    $activityProgress = $content['activity_progress'] ?? [];
                                    $reportMeetings = is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [];
                                    $keyAchievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
                                    $challengesMitigation = $content['challenges_mitigation'] ?? [];

                                            $relatedPlan = \App\Models\Document::findWeeklyPlanForUserAndPeriod(
                                                $document->period_id,
                                                (string) auth()->id()
                                            );
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
                                    $canProcessWeeklyBudgets = auth()->user()?->can('process weekly budgets') ?? false;
                                    $targetStatusValue = old('target_accomplished_status');
                                    if ((! is_string($targetStatusValue) || trim($targetStatusValue) === '') && old('target_accomplished') !== null) {
                                        $legacyTargetAccomplished = trim((string) old('target_accomplished'));
                                        if ($legacyTargetAccomplished === '1') {
                                            $targetStatusValue = 'completed';
                                        } elseif ($legacyTargetAccomplished === '0') {
                                            $targetStatusValue = 'not_completed';
                                        }
                                    }
                                    if (! is_string($targetStatusValue) || trim($targetStatusValue) === '') {
                                        $targetStatusValue = is_array($content) ? trim((string)($content['target_accomplished_status'] ?? '')) : '';
                                    }
                                    if ($targetStatusValue === '' || ! in_array($targetStatusValue, ['completed', 'partially_completed', 'not_completed'], true)) {
                                        if ($document->target_accomplished === true || $document->target_accomplished === 1 || $document->target_accomplished === '1') {
                                            $targetStatusValue = 'completed';
                                        } elseif ($document->target_accomplished === false || $document->target_accomplished === 0 || $document->target_accomplished === '0') {
                                            $targetStatusValue = 'not_completed';
                                        } else {
                                            $targetStatusValue = '';
                                        }
                                    }
                                    $storedTargetStatus = is_array($content) ? trim((string) ($content['target_accomplished_status'] ?? '')) : '';
                                    $preferLegacyBooleanOption = $storedTargetStatus === ''
                                        && old('target_accomplished_status') === null
                                        && ($document->target_accomplished === true
                                            || $document->target_accomplished === false
                                            || $document->target_accomplished === 1
                                            || $document->target_accomplished === 0
                                            || $document->target_accomplished === '1'
                                            || $document->target_accomplished === '0');
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
                                            <label for="target_accomplished_status" class="form-label">
                                                Target accomplishment status <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control @error('target_accomplished_status') is-invalid @enderror"
                                                name="target_accomplished_status"
                                                id="target_accomplished_status"
                                                required>
                                                <option value="">-- Select Status --</option>
                                                <option value="1" {{ $preferLegacyBooleanOption && $targetStatusValue === 'completed' ? 'selected' : '' }}>Completed</option>
                                                <option value="0" {{ $preferLegacyBooleanOption && $targetStatusValue === 'not_completed' ? 'selected' : '' }}>Not completed</option>
                                                <option value="completed" {{ (! $preferLegacyBooleanOption) && $targetStatusValue === 'completed' ? 'selected' : '' }}>Completed</option>
                                                <option value="partially_completed" {{ $targetStatusValue === 'partially_completed' ? 'selected' : '' }}>Partially completed</option>
                                                <option value="not_completed" {{ (! $preferLegacyBooleanOption) && $targetStatusValue === 'not_completed' ? 'selected' : '' }}>Not completed</option>
                                            </select>
                                            @error('target_accomplished_status')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                            @php
                                                $showTargetExplanation = in_array($targetStatusValue, ['partially_completed', 'not_completed'], true);
                                            @endphp
                                            <div class="mb-0" id="targetExplanationContainer" @if(!$showTargetExplanation) style="display:none;" @endif>
                                            <label for="target_explanation" class="form-label">
                                                Why was the target not accomplished?
                                            </label>
                                            <textarea class="form-control @error('target_explanation') is-invalid @enderror"
                                                name="target_explanation"
                                                id="target_explanation"
                                                rows="5"
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
                                                rows="5"
                                                placeholder="Describe what this week focused on (and the overall progress)">{{ old('content.summary', $objectiveSummary) }}</textarea>
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
                                                <table class="table table-bordered table-striped align-middle mb-0">
                                                    <thead class="wh-thead-weekly-report">
                                                        <tr>
                                                            <th style="width: 60px;">No.</th>
                                                            <th style="width: 320px;">Activity (from Weekly Plan)</th>
                                                            <th style="width: 150px;">Progress</th>
                                                            <th>Remarks/Challenges</th>
                                                            <th>Next Step</th>
                                                            <th style="width: 50px;"></th>
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
                                                                        data-off-plan-select="true"
                                                                    >
                                                                        <option value="">-- Select Activity --</option>
                                                                        @foreach($planActivities as $planActivity)
                                                                            <option value="{{ $planActivity['label'] }}" {{ $selectedActivityOutput === $planActivity['label'] ? 'selected' : '' }}>
                                                                                {{ $planActivity['label'] }}
                                                                            </option>
                                                                        @endforeach
                                                                        <option value="__off_plan__" {{ $selectedActivityOutput === '__off_plan__' ? 'selected' : '' }}>Other (Not on Plan)</option>
                                                                        @if($selectedActivityOutput !== '' && $selectedActivityOutput !== '__off_plan__' && !collect($planActivities)->contains(fn($a) => ($a['label'] ?? '') === $selectedActivityOutput))
                                                                            <option value="{{ $selectedActivityOutput }}" selected>{{ $selectedActivityOutput }}</option>
                            @endif
                                                                    </select>
                                                                    @php
                                                                        $offPlanActivity = old("content.activity_progress.$i.off_plan_activity", $row['off_plan_activity'] ?? '');
                                                                        $showOffPlanActivity = $selectedActivityOutput === '__off_plan__';
                                                                    @endphp
                                                                    <div class="mt-2" data-off-plan-input-wrap="true" @if(!$showOffPlanActivity) style="display:none;" @endif>
                                                                        <input
                                                                            type="text"
                                                                            class="form-control"
                                                                            name="content[activity_progress][{{ $i }}][off_plan_activity]"
                                                                            value="{{ $offPlanActivity }}"
                                                                            placeholder="Describe off-plan activity"
                                                                            @if($showOffPlanActivity) required @endif
                                                                        >
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <select class="form-select"
                                                                        name="content[activity_progress][{{ $i }}][progress]">
                                                                        <option value="">-- Select --</option>
                                                                        @php
                                                                            $cur = old("content.activity_progress.$i.progress", $row['progress'] ?? '');
                                                                            $progressOptions = [
                                                                                'Done' => 'Done',
                                                                                'Partially done' => 'Carry to next week',
                                                                                'Not started' => 'Not done',
                                                                            ];
                                                                        @endphp
                                                                        @foreach($progressOptions as $value => $label)
                                                                            <option value="{{ $value }}" {{ $cur === $value ? 'selected' : '' }}>{{ $label }}</option>
                                                                        @endforeach
                                                                        @if($cur !== '' && ! array_key_exists($cur, $progressOptions))
                                                                            <option value="{{ $cur }}" selected>{{ $cur }}</option>
                                                                        @endif
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[activity_progress][{{ $i }}][remarks_challenges]"
                                                                        placeholder="Remarks/Challenges">{{ old("content.activity_progress.$i.remarks_challenges", $row['remarks_challenges'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
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
                                                <h6 class="mb-0"><i class="ti ti-users me-2"></i>3. Meetings Progress</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addReportMeetingRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Meeting
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped align-middle mb-0">
                                                    <thead class="wh-thead-weekly-report">
                                                        <tr>
                                                            <th style="width: 160px;">Date</th>
                                                            <th>Meeting / Engagement</th>
                                                            <th style="width: 220px;">Participant(s)</th>
                                                            <th style="width: 170px;">Status</th>
                                                            <th style="width: 50px;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="reportMeetingsBody">
                                                        @php
                                                            $resolvedReportMeetingRows = old('content.key_meetings', $reportMeetingRows ?? $reportMeetings ?? []);
                                                            if (!is_array($resolvedReportMeetingRows) || empty($resolvedReportMeetingRows)) {
                                                                $resolvedReportMeetingRows = [['date' => '', 'meeting' => '', 'participant_user_ids' => [], 'status' => '', 'location' => '', 'reactions_notes' => '', 'source_type' => 'report_manual', 'source_plan_id' => '', 'source_owner_user_id' => (string) auth()->id(), 'is_readonly_for_current_user' => false]];
                                                            }
                                                        @endphp
                                                        @foreach($resolvedReportMeetingRows as $i => $meetingRow)
                                                            @php
                                                                $meetingRow = is_array($meetingRow) ? $meetingRow : [];
                                                                $readonlyRaw = old("content.key_meetings.$i.is_readonly_for_current_user", $meetingRow['is_readonly_for_current_user'] ?? false);
                                                                $isReadonlyMeeting = in_array((string) $readonlyRaw, ['1', 'true', 'yes', 'on'], true) || $readonlyRaw === true || $readonlyRaw === 1;
                                                                $sourceType = old("content.key_meetings.$i.source_type", $meetingRow['source_type'] ?? 'report_manual');
                                                                $sourcePlanId = old("content.key_meetings.$i.source_plan_id", $meetingRow['source_plan_id'] ?? '');
                                                                $sourceOwnerUserId = old("content.key_meetings.$i.source_owner_user_id", $meetingRow['source_owner_user_id'] ?? (string) auth()->id());
                                                            @endphp
                                                            <tr data-report-meeting-readonly="{{ $isReadonlyMeeting ? '1' : '0' }}">
                                                                <td>
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][meeting_uid]" value="{{ old("content.key_meetings.$i.meeting_uid", $meetingRow['meeting_uid'] ?? '') }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][source_type]" value="{{ $sourceType }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][source_plan_id]" value="{{ $sourcePlanId }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][source_owner_user_id]" value="{{ $sourceOwnerUserId }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][is_readonly_for_current_user]" value="{{ $isReadonlyMeeting ? '1' : '0' }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][location]" value="{{ old("content.key_meetings.$i.location", $meetingRow['location'] ?? '') }}">
                                                                    <input type="hidden" name="content[key_meetings][{{ $i }}][reactions_notes]" value="{{ old("content.key_meetings.$i.reactions_notes", $meetingRow['reactions_notes'] ?? '') }}">
                                                                    @if($isReadonlyMeeting)
                                                                        <input type="hidden" name="content[key_meetings][{{ $i }}][date]" value="{{ old("content.key_meetings.$i.date", $meetingRow['date'] ?? '') }}">
                                                                    @endif
                                                                    <input type="date" class="form-control"
                                                                        @unless($isReadonlyMeeting) name="content[key_meetings][{{ $i }}][date]" @endunless
                                                                        value="{{ old("content.key_meetings.$i.date", $meetingRow['date'] ?? '') }}"
                                                                        @if($isReadonlyMeeting) disabled @endif>
                                                                </td>
                                                                <td>
                                                                    <input class="form-control"
                                                                        name="content[key_meetings][{{ $i }}][meeting]"
                                                                        value="{{ old("content.key_meetings.$i.meeting", $meetingRow['meeting'] ?? '') }}"
                                                                        placeholder="Meeting / engagement"
                                                                        @if($isReadonlyMeeting) disabled @endif>
                                                                    @if($isReadonlyMeeting)
                                                                        <small class="badge bg-light-warning text-warning mt-1">Assigned (View only)</small>
                                                                    @endif
                                                                </td>
                                                                <td>
                                                                    @php
                                                                        $selectedParticipantIds = old("content.key_meetings.$i.participant_user_ids", $meetingRow['participant_user_ids'] ?? []);
                                                                        if (!is_array($selectedParticipantIds)) $selectedParticipantIds = [];
                                                                    @endphp
                                                                    <select class="form-select" multiple name="content[key_meetings][{{ $i }}][participant_user_ids][]" @if($isReadonlyMeeting) disabled @endif>
                                                                        @foreach(($teamUsers ?? collect()) as $teamUser)
                                                                            <option value="{{ $teamUser->id }}" {{ in_array((string)$teamUser->id, array_map('strval', $selectedParticipantIds), true) ? 'selected' : '' }}>
                                                                                {{ $teamUser->name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </td>
                                                                <td>
                                                                    @if($isReadonlyMeeting)
                                                                        <input type="hidden" name="content[key_meetings][{{ $i }}][status]" value="{{ old("content.key_meetings.$i.status", $meetingRow['status'] ?? '') }}">
                                                                    @endif
                                                                    <select class="form-select" @unless($isReadonlyMeeting) name="content[key_meetings][{{ $i }}][status]" @endunless @if($isReadonlyMeeting) disabled @endif>
                                                                        <option value="">-- Select --</option>
                                                                        <option value="Done" {{ old("content.key_meetings.$i.status", $meetingRow['status'] ?? '') === 'Done' ? 'selected' : '' }}>Done</option>
                                                                        <option value="Not done" {{ old("content.key_meetings.$i.status", $meetingRow['status'] ?? '') === 'Not done' ? 'selected' : '' }}>Not done</option>
                                                                    </select>
                                                                </td>
                                                                <td class="text-center align-middle">
                                                                    @if(!$isReadonlyMeeting)
                                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-report-meeting-row">&times;</button>
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

                                <!-- 4. Key Achievements -->
                                <div class="col-12 mb-3">
                                    <div class="card border-secondary">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-check-square me-2"></i>4. Key Achievements</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addAchievementRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Achievement
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped align-middle mb-0">
                                                    <thead class="wh-thead-weekly-report">
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
                                                            @php
                                                                $achievementText = is_string($achievement)
                                                                    ? $achievement
                                                                    : (is_array($achievement) ? (string) ($achievement['achievement'] ?? '') : '');
                                                            @endphp
                                                            <tr>
                                                                <td class="text-center align-middle js-achievement-row-no">{{ $i + 1 }}</td>
                                                                <td>
                                                                    <textarea class="form-control @error("content.key_achievements.$i") is-invalid @enderror"
                                                                        name="content[key_achievements][{{ $i }}]"
                                                                        rows="5"
                                                                        placeholder="Achievement">{{ old("content.key_achievements.$i", $achievementText) }}</textarea>
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

                                <!-- 5. Challenges & Mitigation -->
                                <div class="col-12 mb-3">
                                    <div class="card border-warning">
                                        <div class="card-header bg-white border-bottom">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><i class="ti ti-alert-triangle me-2"></i>5. Challenges & Mitigation</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="addChallengeRowBtn">
                                                    <i class="ti ti-plus me-1"></i> Add Challenge
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-striped align-middle mb-0">
                                                    <thead class="wh-thead-weekly-report">
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
                                                                    <textarea class="form-control" rows="5"
                                                                        name="content[challenges_mitigation][{{ $i }}][challenge]"
                                                                        placeholder="Challenge">{{ old("content.challenges_mitigation.$i.challenge", $chRow['challenge'] ?? '') }}</textarea>
                                                                </td>
                                                                <td>
                                                                    <textarea class="form-control" rows="5"
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

                                <!-- 6. Budget -->
                                <div class="col-12 mb-3">
                                    <div class="card border-success">
                                        <div class="card-header bg-white border-bottom">
                                            <h6 class="mb-0"><i class="ti ti-dollar-sign me-2"></i>6. Budget (from weekly plan)</h6>
                                        </div>
                                        <div class="card-body">
                                            @php
                                                // Use plan budget items and match by budget_uid where possible
                                                $planBudgetFiltered = is_array($planBudget) ? array_values(array_filter($planBudget, function ($row) {
                                                    if (!is_array($row)) return false;
                                                    return trim((string)($row['item'] ?? '')) !== ''
                                                        || trim((string)($row['description_purpose'] ?? '')) !== ''
                                                        || trim((string)($row['amount'] ?? '')) !== '';
                                                })) : [];
                                                $relatedPlanReviewsByUid = [];
                                                if ($relatedPlan) {
                                                    $relatedPlanReviewsByUid = $relatedPlan->budgetReviewItemsByUid();
                                                }
                                                // Index existing budget_statuses by budget_uid if available, else by position
                                                $statusByUid = [];
                                                foreach ($budgetStatuses as $sIdx => $s) {
                                                    $sUid = is_array($s) ? trim((string)($s['budget_uid'] ?? '')) : '';
                                                    if ($sUid !== '') {
                                                        $statusByUid[$sUid] = $s;
                                                    }
                                                }
                                            @endphp

                                            @if(count($planBudgetFiltered) > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-bordered table-striped align-middle mb-0">
                                                        <thead class="wh-thead-weekly-report">
                                                            <tr>
                                                                <th>Budget Item / Activity</th>
                                                                <th>Description / Purpose</th>
                                                                <th style="width: 200px;">Amount</th>
                                                                <th style="width: 140px;">Review</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($planBudgetFiltered as $i => $planRow)
                                                                @php
                                                                    $planRow = is_array($planRow) ? $planRow : [];
                                                                    $uid = trim((string)($planRow['budget_uid'] ?? ''));
                                                                    $review = $uid !== '' ? ($relatedPlanReviewsByUid[$uid] ?? null) : null;
                                                                    $reviewDecision = is_array($review) ? ($review['decision'] ?? null) : null;

                                                                    // Try to find existing status by uid first, then by index
                                                                    $statusRow = ($uid !== '' && isset($statusByUid[$uid])) ? $statusByUid[$uid] : ($budgetStatuses[$i] ?? []);
                                                                    $statusRow = is_array($statusRow) ? $statusRow : [];

                                                                    // Auto-prefill from review if staff hasn't set a status yet
                                                                    $currentStatus = old("content.budget_statuses.$i.status", $statusRow['status'] ?? '');
                                                                    if ($currentStatus === '' && $reviewDecision === 'approved') $currentStatus = 'Received';
                                                                    elseif ($currentStatus === '' && $reviewDecision === 'pending') $currentStatus = 'Pending';
                                                                @endphp
                                                                <tr>
                                                                    <td>
                                                                        <input class="form-control" type="text" value="{{ $planRow['item'] ?? '' }}" disabled>
                                                                        <input type="hidden" name="content[budget_statuses][{{ $i }}][budget_uid]" value="{{ $uid }}">
                                                                    </td>
                                                                    <td>
                                                                        <input class="form-control" type="text" value="{{ $planRow['description_purpose'] ?? '' }}" disabled>
                                                                    </td>
                                                                    <td>
                                                                        <input class="form-control" type="text" value="{{ $planRow['amount'] ?? '' }}" disabled>
                                                                    </td>
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
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <div class="alert alert-info mb-0">No budget items found in the related weekly plan.</div>
                                            @endif
                                            <small class="form-text text-muted mt-2">
                                                @if($canProcessWeeklyBudgets)
                                                    Budget statuses reflect outcomes for the budget items defined in your weekly plan. Review decisions are auto-prefilled but you can adjust.
                                                @else
                                                    Budget statuses are maintained by finance reviewers and are shown here for reference.
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
