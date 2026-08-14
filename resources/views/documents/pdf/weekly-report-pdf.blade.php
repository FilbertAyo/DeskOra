<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            font-family: 'Times New Roman', Times-Roman, Times, serif;
            font-size: 10pt;
            color: #000;
            margin: 0;
            text-align: justify;
        }
        h2 {
            margin: 0 0 4px 0;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
        }
        .doc-subtitle {
            margin: 0 0 14px 0;
            text-align: center;
            font-size: 10pt;
            font-weight: normal;
        }
        .section-title {
            margin-top: 14px;
            margin-bottom: 6px;
            font-size: 11pt;
            font-weight: bold;
            text-align: left;
        }
        .summary-text {
            margin-bottom: 10px;
            text-align: justify;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            table-layout: fixed;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        td {
            text-align: justify;
        }
        th {
            background: #f5f5f5;
            text-align: left;
        }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    @php
        $content = is_array($document->content) ? $document->content : [];
        $summary = trim((string) ($content['summary'] ?? ''));
        $activityProgress = \App\Models\Document::sortWeeklyReportActivityProgress(
            is_array($content['activity_progress'] ?? null) ? $content['activity_progress'] : []
        );
        $reportMeetings = is_array($reportMeetingRows ?? null) && $reportMeetingRows !== []
            ? $reportMeetingRows
            : (is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : []);
        $keyAchievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
        $challengesMitigation = $content['challenges_mitigation'] ?? [];

        $relatedPlan = \App\Models\Document::findWeeklyPlanForUserAndPeriod(
            $document->period_id,
            (string) $document->user_id
        );
        $planBudget = is_array($relatedPlan?->content ?? null) ? ($relatedPlan->content['budget'] ?? []) : [];

        $targetStatus = trim((string) ($content['target_accomplished_status'] ?? ''));
        if (! in_array($targetStatus, ['completed', 'partially_completed', 'not_completed'], true)) {
            if ($document->target_accomplished === true || $document->target_accomplished === 1 || $document->target_accomplished === '1') {
                $targetStatus = 'completed';
            } elseif ($document->target_accomplished === false || $document->target_accomplished === 0 || $document->target_accomplished === '0') {
                $targetStatus = 'not_completed';
            } else {
                $targetStatus = '';
            }
        }

        $targetStatusLabel = match ($targetStatus) {
            'completed' => 'Completed',
            'partially_completed' => 'Partially completed',
            'not_completed' => 'Not completed',
            default => '',
        };

        $hasTargetAccomplishment = ($relatedPlan && trim((string) ($relatedPlan->main_target ?? '')) !== '')
            || $targetStatus !== '';

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

        $achievementRows = array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return trim($item);
            }
            if (is_array($item)) {
                return trim((string) ($item['achievement'] ?? ''));
            }

            return '';
        }, $keyAchievements)));
        $hasAchievements = count($achievementRows) > 0;

        $hasReportMeetings = collect($reportMeetings ?: [])->contains(function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (['date', 'meeting', 'participant_user_ids', 'participants', 'status', 'location', 'reactions_notes'] as $k) {
                $value = $row[$k] ?? null;
                if (is_array($value)) {
                    if (count(array_filter(array_map(static fn ($item) => trim((string) $item), $value))) > 0) {
                        return true;
                    }
                } elseif (trim((string) $value) !== '') {
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

        $planBudgetFiltered = is_array($planBudget) ? array_values(array_filter($planBudget, function ($row) {
            if (!is_array($row)) {
                return false;
            }

            return trim((string) ($row['item'] ?? '')) !== ''
                || trim((string) ($row['description_purpose'] ?? '')) !== ''
                || trim((string) ($row['amount'] ?? '')) !== '';
        })) : [];
        $planBudgetTotal = 0.0;
        foreach ($planBudgetFiltered as $planBudgetRow) {
            $planBudgetRow = is_array($planBudgetRow) ? $planBudgetRow : [];
            $planBudgetTotal += \App\Models\Document::parseBudgetAmount((string) ($planBudgetRow['amount'] ?? ''));
        }
        $hasBudgetStatus = count($planBudgetFiltered) > 0;

        $period = $document->period;
        $staffName = $document->user?->name ?? '';
        $periodDates = $period?->date_range ?? '';
        $subtitle = 'Weekly Report';
        if ($period) {
            $subtitle .= ' - Week '.$period->week_number;
            if ($periodDates !== '') {
                $subtitle .= ' ('.$periodDates.')';
            }
        }
        if ($staffName !== '') {
            $subtitle .= ' | '.$staffName;
        }

        $mainTargetText = ($relatedPlan && trim((string) ($relatedPlan->main_target ?? '')) !== '')
            ? $relatedPlan->main_target
            : '';
        $statusCell = $targetStatusLabel;
        if (in_array($targetStatus, ['partially_completed', 'not_completed'], true) && trim((string) ($document->target_explanation ?? '')) !== '') {
            $statusCell = trim($statusCell.' — '.$document->target_explanation);
        }
    @endphp

    <h2>Weekly Report</h2>
    <p class="doc-subtitle">{{ $subtitle }}</p>

    @if($hasTargetAccomplishment)
        <table>
            <thead>
                <tr>
                    <th>Main Target</th>
                    <th style="width: 28%;">Accomplishment Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $mainTargetText !== '' ? $mainTargetText : '-' }}</td>
                    <td>{{ $targetStatus !== '' ? $statusCell : '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($summary !== '')
        <div class="section-title">Summary of the Week</div>
        <p class="summary-text">{{ $summary }}</p>
    @endif

    @if($hasActivityProgress)
        <div class="section-title">Weekly Activity Progress</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">No.</th>
                    <th>Activity/Output</th>
                    <th style="width: 9%;">Progress</th>
                    <th>Remarks/Challenges</th>
                    <th style="width: 22%;">Next Step</th>
                </tr>
            </thead>
            <tbody>
                @php $apNum = 0; @endphp
                @foreach(($activityProgress ?: []) as $row)
                    @php
                        $row = is_array($row) ? $row : [];
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
                        $carryToNextPlan = in_array(trim((string) ($row['progress'] ?? '')), ['Partially done', 'Done and carry'], true)
                            || (bool) ($row['carry_to_next_plan'] ?? false);
                        if ($activityOutputRaw === '__off_plan__') {
                            $activityOutputDisplay = $offPlanActivity !== '' ? ('Other (Not on Plan): '.$offPlanActivity) : 'Other (Not on Plan)';
                        } elseif ($activityOutputRaw === '' && $offPlanActivity !== '') {
                            $activityOutputDisplay = 'Other (Not on Plan): '.$offPlanActivity;
                        }
                    @endphp
                    @if(!$apEmpty)
                        @php
                            $apNum++;
                            $progressRaw = trim((string) ($row['progress'] ?? ''));
                            $progressDisplay = match ($progressRaw) {
                                'Partially done' => 'Carry to next week',
                                'Done and carry' => 'Done (recurring)',
                                'Not started' => 'Not done',
                                default => $progressRaw,
                            };
                        @endphp
                        <tr>
                            <td class="text-center">{{ $apNum }}</td>
                            <td>{{ $activityOutputDisplay }}</td>
                            <td>{{ $progressDisplay }}</td>
                            <td>{{ $row['remarks_challenges'] ?? '' }}</td>
                            <td>
                                {{ $row['next_step'] ?? '' }}
                                @if($carryToNextPlan)
                                    (Will be carried to next weekly plan)
                                @endif
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasAchievements)
        <div class="section-title">Key Achievements</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">No.</th>
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
    @endif

    @if($hasReportMeetings)
        <div class="section-title">Meetings Progress</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Date</th>
                    <th>Meeting / Engagement</th>
                    <th style="width: 18%;">Participant(s)</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 12%;">Location</th>
                    <th>Reactions / Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($reportMeetings ?: []) as $meetingRow)
                    @php
                        $meetingRow = is_array($meetingRow) ? $meetingRow : [];
                        $participantIds = is_array($meetingRow['participant_user_ids'] ?? null) ? $meetingRow['participant_user_ids'] : [];
                        $participantIds = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $participantIds)));
                        $participantIdSet = array_fill_keys($participantIds, true);
                        $participantNames = collect(($teamUsers ?? collect()))
                            ->filter(static fn ($u) => isset($participantIdSet[(string) $u->id]))
                            ->pluck('name')
                            ->values()
                            ->all();
                        $legacyParticipantsText = trim((string) ($meetingRow['participants'] ?? ''));
                        $meetingEmpty = trim((string) ($meetingRow['date'] ?? '')) === ''
                            && trim((string) ($meetingRow['meeting'] ?? '')) === ''
                            && trim((string) ($meetingRow['status'] ?? '')) === ''
                            && trim((string) ($meetingRow['location'] ?? '')) === ''
                            && trim((string) ($meetingRow['reactions_notes'] ?? '')) === ''
                            && count($participantIds) === 0
                            && $legacyParticipantsText === '';
                    @endphp
                    @if(!$meetingEmpty)
                        <tr>
                            <td>{{ $meetingRow['date'] ?? '' }}</td>
                            <td>
                                {{ $meetingRow['meeting'] ?? '' }}
                                @if(in_array((string) ($meetingRow['source_type'] ?? ''), ['assigned_plan', 'assigned_report'], true))
                                    (Assigned)
                                @endif
                            </td>
                            <td>{{ ! empty($participantNames) ? implode(', ', $participantNames) : $legacyParticipantsText }}</td>
                            <td>{{ trim((string) ($meetingRow['status'] ?? '')) }}</td>
                            <td>{{ $meetingRow['location'] ?? '' }}</td>
                            <td>{{ $meetingRow['reactions_notes'] ?? '' }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasChallengesMitigation)
        <div class="section-title">Challenges &amp; Mitigation</div>
        <table>
            <thead>
                <tr>
                    <th>Challenge(s)</th>
                    <th style="width: 35%;">Action Taken/Planned</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($challengesMitigation ?: []) as $row)
                    @php
                        $row = is_array($row) ? $row : [];
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
            </tbody>
        </table>
    @endif

    @if($hasBudgetStatus)
        <div class="section-title">Budget Status (from weekly plan)</div>
        <table>
            <thead>
                <tr>
                    <th>Budget Item</th>
                    <th>Description / Purpose</th>
                    <th style="width: 18%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($planBudgetFiltered as $planRow)
                    @php
                        $planRow = is_array($planRow) ? $planRow : [];
                    @endphp
                    <tr>
                        <td>{{ $planRow['item'] ?? '' }}</td>
                        <td>{{ $planRow['description_purpose'] ?? '' }}</td>
                        <td>{{ $planRow['amount'] ?? '' }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">Total</td>
                    <td class="text-end">{{ number_format($planBudgetTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
