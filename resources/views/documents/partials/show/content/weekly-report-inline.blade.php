                                @php
                                    $summary = $content['summary'] ?? '';
                                    $activityProgress = $content['activity_progress'] ?? [];
                                    $keyAchievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
                                    $challengesMitigation = $content['challenges_mitigation'] ?? [];
                                    $budgetStatuses = $content['budget_statuses'] ?? [];

                                    $relatedPlan = \App\Models\Document::findWeeklyPlanForUserAndPeriod(
                                        $document->period_id,
                                        (string) $document->user_id
                                    );
                                    $planBudget = is_array($relatedPlan?->content ?? null) ? ($relatedPlan->content['budget'] ?? []) : [];

                                    $hasActivityProgress = collect($activityProgress ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['activity_output', 'off_plan_activity', 'progress', 'remarks_challenges', 'next_step'] as $k) {
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
                                                                    foreach (['activity_output', 'off_plan_activity', 'progress', 'remarks_challenges', 'next_step'] as $apk) {
                                                                        if (trim((string) ($row[$apk] ?? '')) !== '') {
                                                                            $apEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                    $activityOutputRaw = trim((string) ($row['activity_output'] ?? ''));
                                                                    $offPlanActivity = trim((string) ($row['off_plan_activity'] ?? ''));
                                                                    $activityOutputDisplay = $activityOutputRaw;
                                                                    if ($activityOutputRaw === '__off_plan__') {
                                                                        $activityOutputDisplay = $offPlanActivity !== '' ? ('Other (Not on Plan): '.$offPlanActivity) : 'Other (Not on Plan)';
                                                                    } elseif ($activityOutputRaw === '' && $offPlanActivity !== '') {
                                                                        $activityOutputDisplay = 'Other (Not on Plan): '.$offPlanActivity;
                                                                    }
                                                                @endphp
                                                                @if(!$apEmpty)
                                                                    @php $apNum++; @endphp
                                                                    <tr>
                                                                        <td class="text-center">{{ $apNum }}</td>
                                                                        <td>{{ $activityOutputDisplay }}</td>
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
