@once
    <style>
        #reportActivityProgressBody td.weekly-report-activity-col,
        th.weekly-report-activity-col {
            min-width: 16rem;
            width: 32%;
            max-width: none;
            white-space: normal;
            vertical-align: top;
        }
        .weekly-report-activity-text {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.45;
        }
        .weekly-report-activity-locked {
            padding: 0.35rem 0.15rem;
        }
    </style>
@endonce

@php
    $activityIndex = (int) ($activityIndex ?? 0);
    $selectedActivityOutput = (string) ($selectedActivityOutput ?? '');
    $offPlanActivity = (string) ($offPlanActivity ?? '');
    $isLockedPlanActivity = $selectedActivityOutput !== '' && $selectedActivityOutput !== '__off_plan__';
@endphp

@if($isLockedPlanActivity)
    <div class="weekly-report-activity-locked">
        <div class="weekly-report-activity-text">{{ $selectedActivityOutput }}</div>
        <input type="hidden" name="content[activity_progress][{{ $activityIndex }}][activity_output]" value="{{ $selectedActivityOutput }}">
    </div>
@else
    <div class="small text-muted mb-1">Not on weekly plan</div>
    <input type="hidden" name="content[activity_progress][{{ $activityIndex }}][activity_output]" value="__off_plan__">
    <textarea
        class="form-control weekly-report-activity-text"
        rows="4"
        name="content[activity_progress][{{ $activityIndex }}][off_plan_activity]"
        placeholder="Describe the activity"
    >{{ $offPlanActivity }}</textarea>
@endif
