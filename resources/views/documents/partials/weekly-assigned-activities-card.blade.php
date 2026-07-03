@php
    $assignedRows = collect($assignedWeeklyActivities ?? [])->filter(function ($assignment) {
        $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];

        return trim((string) ($payload['activity'] ?? '')) !== '';
    })->values();
    $assignmentOwner = $assignedToUser ?? null;
    $isAssignedToCurrentUser = !auth()->check()
        || !$assignmentOwner
        || (string) $assignmentOwner->id === (string) auth()->id();
    $assignmentTitle = $isAssignedToCurrentUser
        ? 'Assigned by Others'
        : 'Assigned to '.($assignmentOwner->name ?? 'Staff').' by Others';
    $periodLabel = isset($assignmentPeriod) && $assignmentPeriod
        ? 'Week '.$assignmentPeriod->week_number.' of '.$assignmentPeriod->year
        : null;
    $assignmentSubtitle = $isAssignedToCurrentUser
        ? 'Tasks from weekly plans where another staff member assigned you'
        : 'Tasks from weekly plans where another staff member assigned '.($assignmentOwner->name ?? 'this staff member');
    if ($periodLabel) {
        $assignmentSubtitle .= ' · '.$periodLabel;
    }
    $hasMultipleAssignees = $assignedRows
        ->pluck('assignee_user_id')
        ->filter()
        ->unique()
        ->count() > 1;
@endphp

@if($assignedRows->isNotEmpty())
    <div class="{{ ($embedded ?? false) ? 'mb-3' : 'col-12 mb-3' }}">
        @if($embedded ?? false)
            <h6 class="text-info mb-2"><i class="ti ti-clipboard-list me-1"></i>{{ $assignmentTitle }}</h6>
            <small class="text-muted d-block mb-2">{{ $assignmentSubtitle }}</small>
        @else
            <div class="card border-info-subtle">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="ti ti-clipboard-list me-2"></i>{{ $assignmentTitle }}</h6>
                    <small class="text-muted">{{ $assignmentSubtitle }}</small>
                </div>
                <div class="card-body">
        @endif
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 110px;">Status</th>
                                <th style="width: 100px;">Day</th>
                                <th>Activity</th>
                                <th style="width: 120px;">Location</th>
                                @if($hasMultipleAssignees)
                                    <th style="width: 170px;">Assigned To</th>
                                @endif
                                <th>From</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assignedRows as $assignment)
                                @php
                                    $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];
                                    $status = (string) ($assignment->status ?? '');
                                    $isLocked = $status === \App\Models\WeeklyActivityAssignment::STATUS_LOCKED;
                                @endphp
                                <tr>
                                    <td>
                                        <span class="badge {{ $isLocked ? 'bg-light-success border border-success' : 'bg-light-warning border border-warning text-dark' }}">
                                            {{ $isLocked ? 'Locked' : 'Preview' }}
                                        </span>
                                    </td>
                                    <td>{{ $payload['day'] ?? '-' }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $payload['activity'] ?? '-' }}</div>
                                        @if(!empty($payload['notes_inclusion']))
                                            <small class="text-muted">{{ $payload['notes_inclusion'] }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $payload['location'] ?? '-' }}</td>
                                    @if($hasMultipleAssignees)
                                        <td>{{ $assignment->assignee->name ?? '-' }}</td>
                                    @endif
                                    <td>{{ $assignment->sourceUser->name ?? '-' }}</td>
                                  
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
