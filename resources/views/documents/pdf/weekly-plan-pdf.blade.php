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
    </style>
</head>
<body>
    @php
        $content = is_array($document->content) ? $document->content : [];
        $objective = $content['objective'] ?? [];
        $objectiveReflection = trim((string) ($objective['reflection_progress'] ?? ($document->weeklyPlanDomain?->objective_reflection_progress ?? '')));
        $mainTarget = trim((string) ($document->main_target ?? ''));
        $plannedActivities = $content['planned_activities'] ?? [];
        $keyMeetings = $content['key_meetings'] ?? [];
        $incomingMeetingsList = is_array($incomingMeetings ?? null) ? $incomingMeetings : [];
        $budget = $content['budget'] ?? [];
        $carriedActivitiesFromPreviousWeek = is_array($carriedActivities ?? null) ? $carriedActivities : [];

        $plannedTeamIds = collect($plannedActivities)
            ->filter(fn ($row) => is_array($row) && !empty($row['team_member_ids']) && is_array($row['team_member_ids']))
            ->flatMap(fn ($row) => $row['team_member_ids'])
            ->merge(
                collect($carriedActivitiesFromPreviousWeek)
                    ->filter(fn ($row) => is_array($row) && !empty($row['team_member_ids']) && is_array($row['team_member_ids']))
                    ->flatMap(fn ($row) => $row['team_member_ids'])
            )
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $teamUserNames = \App\Models\User::whereIn('id', $plannedTeamIds)->pluck('name', 'id')->toArray();

        $meetingParticipantIds = collect($keyMeetings)
            ->filter(fn ($row) => is_array($row) && !empty($row['participant_user_ids']) && is_array($row['participant_user_ids']))
            ->flatMap(fn ($row) => $row['participant_user_ids'])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $meetingUserNames = \App\Models\User::whereIn('id', $meetingParticipantIds)->pluck('name', 'id')->toArray();

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
                    'activity_uid' => trim((string) ($row['activity_uid'] ?? '')),
                    'carry_over_source_weekly_report_id' => trim((string) ($row['carry_over_source_weekly_report_id'] ?? '')),
                    'carry_over_source_report_id' => trim((string) ($row['carry_over_source_report_id'] ?? '')),
                ];
            })
            ->filter()
            ->values();

        $planAlreadyHasCarriedActivities = $normalizedActivities->contains(
            fn ($row) => trim((string) ($row['carry_over_source_weekly_report_id'] ?? '')) !== ''
        );
        if (! $planAlreadyHasCarriedActivities) {
            $carriedRows = collect($carriedActivitiesFromPreviousWeek)
                ->map(function ($row) {
                    if (!is_array($row)) {
                        return null;
                    }
                    return [
                        'day' => trim((string) ($row['day'] ?? '')),
                        'activity' => trim((string) ($row['activity'] ?? '')),
                        'location' => trim((string) ($row['location'] ?? '')),
                        'notes_inclusion' => trim((string) ($row['notes_inclusion'] ?? '')),
                        'team_member_ids' => is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [],
                        'activity_uid' => trim((string) ($row['activity_uid'] ?? '')),
                        'carry_over_source_weekly_report_id' => trim((string) ($row['carry_over_source_weekly_report_id'] ?? '')),
                        'carry_over_source_report_id' => trim((string) ($row['carry_over_source_report_id'] ?? '')),
                    ];
                })
                ->filter(function ($row) {
                    return is_array($row)
                        && (
                            $row['day'] !== ''
                            || $row['activity'] !== ''
                            || $row['location'] !== ''
                            || $row['notes_inclusion'] !== ''
                            || !empty($row['team_member_ids'])
                        );
                })
                ->values();
            $existingActivityUids = $normalizedActivities
                ->map(fn ($row) => trim((string) ($row['activity_uid'] ?? '')))
                ->filter()
                ->values()
                ->all();
            $existingActivityKeys = $normalizedActivities
                ->map(fn ($row) => mb_strtolower(trim((string) ($row['day'] ?? '')).'|'.trim((string) ($row['activity'] ?? ''))))
                ->filter()
                ->values()
                ->all();
            foreach ($carriedRows as $carryRow) {
                $carryUid = trim((string) ($carryRow['activity_uid'] ?? ''));
                if ($carryUid !== '' && in_array($carryUid, $existingActivityUids, true)) {
                    continue;
                }
                $carryKey = mb_strtolower(trim((string) ($carryRow['day'] ?? '')).'|'.trim((string) ($carryRow['activity'] ?? '')));
                if (in_array($carryKey, $existingActivityKeys, true)) {
                    continue;
                }
                $normalizedActivities->push($carryRow);
                if ($carryUid !== '') {
                    $existingActivityUids[] = $carryUid;
                }
                $existingActivityKeys[] = $carryKey;
            }
        }

        $groupedActivities = collect($dayOrder)
            ->mapWithKeys(fn ($day) => [$day => []])
            ->merge(
                $normalizedActivities
                    ->groupBy(function ($row) use ($dayOrder) {
                        return in_array($row['day'], $dayOrder, true) ? $row['day'] : 'Other';
                    })
                    ->toArray()
            );

        $hasPlannedActivities = $groupedActivities->contains(fn ($rows) => !empty($rows));

        $normalizedMeetings = collect($keyMeetings ?: [])
            ->map(function ($row) {
                if (! is_array($row)) {
                    return null;
                }
                $participantIds = is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [];
                $participantIds = array_values(array_unique(array_filter(array_map('strval', $participantIds))));
                sort($participantIds);

                return [
                    'date' => trim((string) ($row['date'] ?? '')),
                    'meeting' => trim((string) ($row['meeting'] ?? '')),
                    'participants' => trim((string) ($row['participants'] ?? '')),
                    'participant_user_ids' => $participantIds,
                    'location' => trim((string) ($row['location'] ?? '')),
                    'reactions_notes' => trim((string) ($row['reactions_notes'] ?? '')),
                ];
            })
            ->filter()
            ->unique(function ($row) {
                return mb_strtolower(($row['date'] ?? '').'|'.($row['meeting'] ?? '').'|'.($row['location'] ?? '').'|'.implode(',', $row['participant_user_ids'] ?? []));
            })
            ->values();

        $hasKeyMeetings = $normalizedMeetings->contains(function ($row) {
            if (! is_array($row)) {
                return false;
            }
            foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $k) {
                if (trim((string) ($row[$k] ?? '')) !== '') {
                    return true;
                }
            }
            if (! empty($row['participant_user_ids'] ?? [])) {
                return true;
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

        $budgetTotal = 0.0;
        foreach ($budget ?: [] as $budgetRow) {
            if (! is_array($budgetRow)) {
                continue;
            }
            $rowHasContent = false;
            foreach (['item', 'description_purpose', 'amount'] as $budgetKey) {
                if (trim((string) ($budgetRow[$budgetKey] ?? '')) !== '') {
                    $rowHasContent = true;
                    break;
                }
            }
            if ($rowHasContent) {
                $budgetTotal += \App\Models\Document::parseBudgetAmount((string) ($budgetRow['amount'] ?? ''));
            }
        }

        $hasObjective = $mainTarget !== '' || $objectiveReflection !== '';

        $assignedRows = collect($assignedWeeklyActivities ?? [])->filter(function ($assignment) {
            $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];

            return trim((string) ($payload['activity'] ?? '')) !== '';
        })->values();
        $hasAssignedActivities = $assignedRows->isNotEmpty();
        $assignedMeetingRows = collect($incomingMeetingsList ?? [])->filter(function ($row) {
            if (! is_array($row)) {
                return false;
            }

            return trim((string) ($row['meeting'] ?? '')) !== ''
                || trim((string) ($row['date'] ?? '')) !== ''
                || trim((string) ($row['location'] ?? '')) !== '';
        })->values();
        $hasAssignedMeetings = $assignedMeetingRows->isNotEmpty();

        $period = $document->period;
        $staffName = $document->user?->name ?? '';
        $periodDates = $period?->date_range ?? '';
        $subtitle = 'Weekly Plan';
        if ($period) {
            $subtitle .= ' - Week '.$period->week_number;
            if ($periodDates !== '') {
                $subtitle .= ' ('.$periodDates.')';
            }
        }
        if ($staffName !== '') {
            $subtitle .= ' | '.$staffName;
        }
    @endphp

    <h2>Weekly Plan</h2>
    <p class="doc-subtitle">{{ $subtitle }}</p>

    @if($hasObjective)
        <table>
            <thead>
                <tr>
                    <th>Main Target</th>
                    <th>Reflection / Progress</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $mainTarget !== '' ? $mainTarget : '-' }}</td>
                    <td>{{ $objectiveReflection !== '' ? $objectiveReflection : '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($hasPlannedActivities)
        <div class="section-title">Planned Activities</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Day</th>
                    <th>Activity</th>
                    <th style="width: 15%;">Location</th>
                    <th style="width: 18%;">Lead Staff/Team</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groupedActivities as $day => $rows)
                    @if(!empty($rows))
                        @foreach($rows as $idx => $row)
                            <tr>
                                @if($idx === 0)
                                    <td rowspan="{{ count($rows) }}">{{ $day }}</td>
                                @endif
                                <td>{{ $row['activity'] ?? '' }}</td>
                                <td>{{ $row['location'] ?? '' }}</td>
                                <td>
                                    @php
                                        $rowTeamIds = is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [];
                                        $rowTeamNames = collect($rowTeamIds)
                                            ->map(fn ($id) => $teamUserNames[(string) $id] ?? null)
                                            ->filter()
                                            ->values()
                                            ->all();
                                    @endphp
                                    {{ !empty($rowTeamNames) ? implode(', ', $rowTeamNames) : '' }}
                                </td>
                                <td>
                                    {{ $row['notes_inclusion'] ?? '' }}
                                    @if(
                                        trim((string) ($row['carry_over_source_weekly_report_id'] ?? '')) !== ''
                                        || trim((string) ($row['carry_over_source_report_id'] ?? '')) !== ''
                                    )
                                        (Carried from previous week)
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasAssignedActivities)
        <div class="section-title">Assigned by Others</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%;">Day</th>
                    <th>Activity</th>
                    <th style="width: 15%;">Location</th>
                    <th>From</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assignedRows as $assignment)
                    @php
                        $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];
                    @endphp
                    <tr>
                        <td>{{ $payload['day'] ?? '-' }}</td>
                        <td>
                            {{ $payload['activity'] ?? '-' }}
                            @if(!empty($payload['notes_inclusion']))
                                — {{ $payload['notes_inclusion'] }}
                            @endif
                        </td>
                        <td>{{ $payload['location'] ?? '-' }}</td>
                        <td>{{ $assignment->sourceUser->name ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasKeyMeetings)
        <div class="section-title">Key Meetings / Engagements</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 14%;">Date</th>
                    <th>Meeting</th>
                    <th style="width: 20%;">Participants</th>
                    <th style="width: 16%;">Location</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($normalizedMeetings as $row)
                    @php
                        $row = is_array($row) ? $row : [];
                        $kmEmpty = true;
                        foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $kmk) {
                            if (trim((string) ($row[$kmk] ?? '')) !== '') {
                                $kmEmpty = false;
                                break;
                            }
                        }
                        if (!empty($row['participant_user_ids'] ?? [])) {
                            $kmEmpty = false;
                        }
                        $participantNames = collect(is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [])
                            ->map(fn ($id) => $meetingUserNames[(string) $id] ?? null)
                            ->filter()
                            ->values()
                            ->all();
                    @endphp
                    @if(!$kmEmpty)
                        <tr>
                            <td>{{ $row['date'] ?? '' }}</td>
                            <td>{{ $row['meeting'] ?? '' }}</td>
                            <td>{{ !empty($participantNames) ? implode(', ', $participantNames) : ($row['participants'] ?? '') }}</td>
                            <td>{{ $row['location'] ?? '' }}</td>
                            <td>
                                {{ $row['reactions_notes'] ?? '' }}
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasAssignedMeetings)
        @php
            $pdfAssignedMeetingParticipantIds = $assignedMeetingRows
                ->flatMap(fn ($row) => is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [])
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $pdfAssignedMeetingUserNames = $pdfAssignedMeetingParticipantIds === []
                ? []
                : array_merge(
                    $meetingUserNames,
                    \App\Models\User::query()->whereIn('id', $pdfAssignedMeetingParticipantIds)->pluck('name', 'id')->toArray()
                );
        @endphp
        <div class="section-title">Assigned Meetings</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 14%;">Date</th>
                    <th>Meeting</th>
                    <th style="width: 20%;">Participants</th>
                    <th style="width: 16%;">Location</th>
                    <th>From</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assignedMeetingRows as $meetingRow)
                    @php
                        $meetingRow = is_array($meetingRow) ? $meetingRow : [];
                        $participantIds = is_array($meetingRow['participant_user_ids'] ?? null) ? $meetingRow['participant_user_ids'] : [];
                        $participantNames = collect($participantIds)
                            ->map(fn ($id) => $pdfAssignedMeetingUserNames[(string) $id] ?? null)
                            ->filter()
                            ->values()
                            ->all();
                    @endphp
                    <tr>
                        <td>{{ $meetingRow['date'] ?? '-' }}</td>
                        <td>{{ $meetingRow['meeting'] ?? '-' }}</td>
                        <td>{{ $participantNames !== [] ? implode(', ', $participantNames) : '-' }}</td>
                        <td>{{ $meetingRow['location'] ?? '-' }}</td>
                        <td>{{ $meetingRow['incoming_source_user_name'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($hasBudgetRows)
        <div class="section-title">Budget</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 30%;">Budget Item / Activity</th>
                    <th>Description / Purpose</th>
                    <th style="width: 18%;">Amount (TZS)</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($budget ?: []) as $row)
                    @php
                        $row = is_array($row) ? $row : [];
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
                <tr class="fw-bold">
                    <td colspan="2" class="text-end">Total</td>
                    <td class="text-end">{{ number_format($budgetTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
