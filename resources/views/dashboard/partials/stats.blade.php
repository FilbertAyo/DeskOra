<div class="dashboard-stats">
    <div class="dash-stat">
        <p class="dash-stat__label">
            <i class="ti ti-clipboard-list text-primary"></i>Open
        </p>
        <p class="dash-stat__value">{{ $activeActionTaskCount }}</p>
    </div>

    <div class="dash-stat">
        <p class="dash-stat__label">
            <i class="ti ti-users-group text-info"></i>Shared
        </p>
        <p class="dash-stat__value">{{ $sharedWithMeCount }}</p>
        @if($sharedWithMeCount > 0)
            <div class="dash-stat__links">
                @if(isset($availableTypes['weekly_plan']))
                    <a href="{{ route('documents.weekly-plans') }}">
                        <i class="ti ti-target me-1"></i>Weekly plans
                    </a>
                @endif
                @if(isset($availableTypes['weekly_report']))
                    <a href="{{ route('documents.weekly-reports') }}">
                        <i class="ti ti-activity me-1"></i>Weekly reports
                    </a>
                @endif
            </div>
        @endif
    </div>

    <div class="dash-stat">
        <p class="dash-stat__label">
            <i class="ti ti-clipboard-list text-secondary"></i>Assigned
        </p>
        <p class="dash-stat__value">{{ $assignedWeeklyActivityCount }}</p>
    </div>
</div>
