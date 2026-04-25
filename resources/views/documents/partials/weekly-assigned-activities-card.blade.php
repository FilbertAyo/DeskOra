@php
    $assignedRows = collect($assignedWeeklyActivities ?? [])->filter(function ($assignment) {
        $payload = is_array($assignment->activity_payload ?? null) ? $assignment->activity_payload : [];

        return trim((string) ($payload['activity'] ?? '')) !== '';
    })->values();
@endphp

@if($assignedRows->isNotEmpty())
    <div class="col-12 mb-3">
        <div class="card border-info-subtle">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="ti ti-clipboard-list me-2"></i>Assigned To You</h6>
                <small class="text-muted">Read-only from shared weekly plans</small>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 110px;">Status</th>
                                <th style="width: 100px;">Day</th>
                                <th>Activity</th>
                                <th style="width: 120px;">Location</th>
                                <th>From</th>
                                <th style="width: 120px;">Plan</th>
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
                                        <span class="badge {{ $isLocked ? 'bg-success' : 'bg-warning text-dark' }}">
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
                                    <td>{{ $assignment->sourceUser->name ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('documents.show', $assignment->source_document_id) }}" class="btn btn-sm btn-outline-info">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
