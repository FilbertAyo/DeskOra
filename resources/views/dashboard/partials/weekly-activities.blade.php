<div class="dash-panel">
    <div class="dash-panel__header">
        <h6 class="dash-panel__title">
            <i class="ti ti-checklist"></i>My Weekly Activities
        </h6>
        <span class="dash-panel__meta">Today's plan, revisions &amp; shares</span>
    </div>
    <div class="dash-panel__body">
        @if($showTodaysWeeklyTasks && $todaysWeeklyTasks->isNotEmpty())
            <div class="dash-section-block">
                <h6 class="dash-section-label">
                    <i class="ti ti-calendar-event"></i>{{ $todaysDayName }}'s tasks
                </h6>
                <div class="dash-list-stack">
                    @foreach($todaysWeeklyTasks as $task)
                        <div class="dash-list-item">
                            <span class="small text-dark">{{ $task['activity'] }}</span>
                            <small class="text-muted text-nowrap">{{ $task['source_label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($collaborationEvents->isNotEmpty())
            <div class="dash-section-block">
                <h6 class="dash-section-label text-dark">
                    <i class="ti ti-users-group"></i>Shared with you
                </h6>
                @foreach($collaborationEvents as $event)
                    <div class="dash-collab-row">
                        <span class="badge {{ $event['badge_class'] ?? 'bg-light' }} align-self-center">{{ $event['badge_label'] ?? '' }}</span>
                        <div class="flex-grow-1 min-w-0">
                            @if(!empty($event['document_id']))
                                <a href="{{ route('documents.show', $event['document_id']) }}" class="text-decoration-none small d-block">
                                    {{ $event['message'] }}
                                </a>
                            @else
                                <span class="small d-block">{{ $event['message'] }}</span>
                            @endif
                        </div>
                        <small class="text-muted text-nowrap">{{ $event['created_at']->diffForHumans() }}</small>
                    </div>
                @endforeach
            </div>
        @endif

        @if($activeActionTaskCount === 0 && $todaysTasksClear && $collaborationEvents->isEmpty())
            <div class="dash-panel__empty py-3">
                <i class="ti ti-mood-smile text-success"></i>
                <p>Nothing pending right now.</p>
            </div>
        @endif
    </div>
</div>
