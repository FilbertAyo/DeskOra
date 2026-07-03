@include('action-logs.partials.quick-update-modal', [
    'modalId' => 'weeklyReportActionTaskModal',
    'formId' => 'weeklyReportActionTaskForm',
    'triggerClass' => 'js-weekly-report-quick-update-task',
    'teamUsers' => $teamUsers ?? collect(),
])
