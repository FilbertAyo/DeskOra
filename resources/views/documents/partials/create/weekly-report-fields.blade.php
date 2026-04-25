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
                        $plan = \App\Models\Document::findWeeklyPlanForUserAndPeriod(
                            $selectedPeriod->id,
                            (string) auth()->id()
                        );
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
                    rows="5"
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
                rows="5"
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
                                        rows="5"
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
