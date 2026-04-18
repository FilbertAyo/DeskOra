                                @php
                                    $objective = $content['objective'] ?? [];
                                    $plannedActivities = $content['planned_activities'] ?? [];
                                    $keyMeetings = $content['key_meetings'] ?? [];
                                    $budget = $content['budget'] ?? [];
                                    $plannedTeamIds = collect($plannedActivities)
                                        ->filter(fn($row) => is_array($row) && !empty($row['team_member_ids']) && is_array($row['team_member_ids']))
                                        ->flatMap(fn($row) => $row['team_member_ids'])
                                        ->filter()
                                        ->map(fn($id) => (string) $id)
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

                                    $hasKeyMeetings = collect($keyMeetings ?: [])->contains(function ($row) {
                                        if (! is_array($row)) {
                                            return false;
                                        }
                                        foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $k) {
                                            if (trim((string) ($row[$k] ?? '')) !== '') {
                                                return true;
                                            }
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
                                                            <th style="width: 150px;">Status</th>
                                                            <th>Reflection / Progress</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>{{ $document->main_target ?: '-' }}</td>
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
                                                                                    ->map(fn($id) => $teamUserNames[(string) $id] ?? null)
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
                                                                <td colspan="5" class="text-center text-muted py-4">No planned activities for this week.</td>
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
                                                        @if($hasKeyMeetings)
                                                            @foreach(($keyMeetings ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
                                                                    $kmEmpty = true;
                                                                    foreach (['date', 'meeting', 'participants', 'location', 'reactions_notes'] as $kmk) {
                                                                        if (trim((string) ($row[$kmk] ?? '')) !== '') {
                                                                            $kmEmpty = false;
                                                                            break;
                                                                        }
                                                                    }
                                                                @endphp
                                                                @if(!$kmEmpty)
                                                                    <tr>
                                                                        <td>{{ $row['date'] ?? '' }}</td>
                                                                        <td>{{ $row['meeting'] ?? '' }}</td>
                                                                        <td>{{ $row['participants'] ?? '' }}</td>
                                                                        <td>{{ $row['location'] ?? '' }}</td>
                                                                        <td>{{ $row['reactions_notes'] ?? '' }}</td>
                                                                    </tr>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="5" class="text-center text-muted py-4">No meetings or engagements planned for this week.</td>
                                                            </tr>
                                                        @endif
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
                                                            <th style="width: 360px;">Budget Item / Activity</th>
                                                            <th >Description / Purpose</th>
                                                            <th style="width: 180px;">Amount (TZS)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @if($hasBudgetRows)
                                                            @foreach(($budget ?: []) as $row)
                                                                @php $row = is_array($row) ? $row : []; @endphp
                                                                @php
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
                                                        @else
                                                            <tr>
                                                                <td colspan="3" class="text-center text-muted py-4">No budget items for this week.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
