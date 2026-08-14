@php
    $assignedMeetingRows = collect($incomingMeetings ?? [])->filter(function ($row) {
        if (! is_array($row)) {
            return false;
        }
        $title = trim((string) ($row['meeting'] ?? ''));
        $date = trim((string) ($row['date'] ?? ''));
        $location = trim((string) ($row['location'] ?? ''));
        $notes = trim((string) ($row['reactions_notes'] ?? ''));
        $participantIds = is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [];

        return $title !== '' || $date !== '' || $location !== '' || $notes !== '' || $participantIds !== [];
    })->values();
    $assignmentOwner = $assignedToUser ?? null;
    $isAssignedToCurrentUser = !auth()->check()
        || !$assignmentOwner
        || (string) $assignmentOwner->id === (string) auth()->id();
    $assignmentTitle = $isAssignedToCurrentUser
        ? 'Assigned Meetings'
        : 'Meetings assigned to '.($assignmentOwner->name ?? 'Staff');
    $periodLabel = isset($assignmentPeriod) && $assignmentPeriod
        ? 'Week '.$assignmentPeriod->week_number.' of '.$assignmentPeriod->year
        : null;
    $assignmentSubtitle = $isAssignedToCurrentUser
        ? 'Meetings from other staff weekly plans where you were selected as a participant'
        : 'Meetings from other staff weekly plans where '.($assignmentOwner->name ?? 'this staff member').' was selected as a participant';
    if ($periodLabel) {
        $assignmentSubtitle .= ' · '.$periodLabel;
    }
    $assignedMeetingParticipantIds = $assignedMeetingRows
        ->flatMap(fn ($row) => is_array($row['participant_user_ids'] ?? null) ? $row['participant_user_ids'] : [])
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->unique()
        ->values()
        ->all();
    $assignedMeetingUserNames = $assignedMeetingParticipantIds === []
        ? []
        : \App\Models\User::query()->whereIn('id', $assignedMeetingParticipantIds)->pluck('name', 'id')->toArray();
@endphp

@if($assignedMeetingRows->isNotEmpty())
    <div class="{{ ($embedded ?? false) ? 'mb-3' : 'col-12 mb-3' }}">
        @if($embedded ?? false)
            <h6 class="text-info mb-2"><i class="ti ti-users me-1"></i>{{ $assignmentTitle }}</h6>
            <small class="text-muted d-block mb-2">{{ $assignmentSubtitle }}</small>
        @else
            <div class="card border-info-subtle">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="ti ti-users me-2"></i>{{ $assignmentTitle }}</h6>
                    <small class="text-muted">{{ $assignmentSubtitle }}</small>
                </div>
                <div class="card-body">
        @endif
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Date</th>
                                <th>Meeting / Engagement</th>
                                <th style="width: 180px;">Participant(s)</th>
                                <th style="width: 120px;">Location</th>
                                <th>Notes</th>
                                <th style="width: 160px;">From</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignedMeetingRows as $meetingRow)
                                @php
                                    $participantIds = is_array($meetingRow['participant_user_ids'] ?? null) ? $meetingRow['participant_user_ids'] : [];
                                    $participantNames = collect($participantIds)
                                        ->map(fn ($id) => $assignedMeetingUserNames[(string) $id] ?? null)
                                        ->filter()
                                        ->values()
                                        ->all();
                                @endphp
                                <tr>
                                    <td>{{ $meetingRow['date'] ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $meetingRow['meeting'] ?? '-' }}</div>
                                        <small class="badge bg-light-info border border-info text-info mt-1">Assigned (View only)</small>
                                    </td>
                                    <td>{{ $participantNames !== [] ? implode(', ', $participantNames) : '-' }}</td>
                                    <td>{{ $meetingRow['location'] ?? '-' }}</td>
                                    <td>{{ $meetingRow['reactions_notes'] ?? '' }}</td>
                                    <td>{{ $meetingRow['incoming_source_user_name'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        @unless($embedded ?? false)
                </div>
            </div>
        @endunless
    </div>
@endif
