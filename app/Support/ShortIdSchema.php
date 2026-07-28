<?php

namespace App\Support;

final class ShortIdSchema
{
    /** @return list<string> */
    public static function primaryKeyTables(): array
    {
        return [
            'branches',
            'departments',
            'users',
            'work_periods',
            'year_plans',
            'year_plan_activities',
            'requests',
            'petty_requests',
            'requisition_requests',
            'request_approvals',
            'deposits',
            'anonymous_feedback',
            'reminder_configs',
            'reminder_dispatch_logs',
            'weekly_activity_assignments',
            'weekly_plans',
            'weekly_plan_activities',
            'weekly_plan_meetings',
            'weekly_plan_budget_items',
            'weekly_plan_budget_reviews',
            'weekly_reports',
            'weekly_report_activity_progress',
            'weekly_report_meetings',
            'weekly_report_achievements',
            'weekly_report_challenges',
            'weekly_report_budget_statuses',
            'monthly_reports',
            'monthly_report_planned_items',
            'monthly_report_off_plan_items',
            'action_logs',
            'action_log_tasks',
            'weekly_matrices',
            'weekly_matrix_entries',
            'nomination_periods',
            'nomination_participations',
            'nominations',
            'meeting_minutes',
            'official_letters',
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function foreignKeyColumns(): array
    {
        return [
            ['users', 'department_id'],
            ['users', 'branch_id'],
            ['departments', 'parent_id'],
            ['departments', 'head_user_id'],
            ['year_plans', 'created_by'],
            ['year_plan_activities', 'year_plan_id'],
            ['year_plan_activities', 'department_id'],
            ['requests', 'user_id'],
            ['requests', 'department_id'],
            ['petty_requests', 'request_id'],
            ['requisition_requests', 'request_id'],
            ['requisition_items', 'request_id'],
            ['request_approvals', 'request_id'],
            ['request_approvals', 'actor_id'],
            ['deposits', 'user_id'],
            ['deposits', 'department_id'],
            ['organization_reports', 'uploaded_by'],
            ['sessions', 'user_id'],
            ['sub_department_user', 'user_id'],
            ['sub_department_user', 'sub_department_id'],
            ['document_user_shares', 'weekly_plan_id'],
            ['document_user_shares', 'weekly_report_id'],
            ['document_user_shares', 'user_id'],
            ['document_share_events', 'weekly_plan_id'],
            ['document_share_events', 'weekly_report_id'],
            ['document_share_events', 'actor_user_id'],
            ['document_share_events', 'target_user_id'],
            ['weekly_activity_assignments', 'source_weekly_plan_id'],
            ['weekly_activity_assignments', 'source_user_id'],
            ['weekly_activity_assignments', 'assignee_user_id'],
            ['weekly_activity_assignments', 'period_id'],
            ['weekly_plans', 'user_id'],
            ['weekly_plans', 'period_id'],
            ['weekly_plans', 'budget_reviewed_by'],
            ['weekly_plan_activities', 'weekly_plan_id'],
            ['weekly_plan_activities', 'carry_over_source_weekly_report_id'],
            ['weekly_plan_activity_assignees', 'weekly_plan_activity_id'],
            ['weekly_plan_activity_assignees', 'user_id'],
            ['weekly_plan_meetings', 'weekly_plan_id'],
            ['weekly_plan_meetings', 'source_plan_id'],
            ['weekly_plan_meetings', 'source_owner_user_id'],
            ['weekly_plan_meeting_participants', 'weekly_plan_meeting_id'],
            ['weekly_plan_meeting_participants', 'user_id'],
            ['weekly_plan_budget_items', 'weekly_plan_id'],
            ['weekly_plan_budget_reviews', 'weekly_plan_budget_item_id'],
            ['weekly_plan_budget_reviews', 'reviewed_by'],
            ['weekly_plan_budget_reviews', 'approved_by'],
            ['weekly_plan_budget_reviews', 'authorized_by'],
            ['weekly_reports', 'user_id'],
            ['weekly_reports', 'period_id'],
            ['weekly_report_activity_progress', 'weekly_report_id'],
            ['weekly_report_meetings', 'weekly_report_id'],
            ['weekly_report_meetings', 'source_plan_id'],
            ['weekly_report_meetings', 'source_owner_user_id'],
            ['weekly_report_meeting_participants', 'weekly_report_meeting_id'],
            ['weekly_report_meeting_participants', 'user_id'],
            ['weekly_report_achievements', 'weekly_report_id'],
            ['weekly_report_challenges', 'weekly_report_id'],
            ['weekly_report_budget_statuses', 'weekly_report_id'],
            ['monthly_reports', 'user_id'],
            ['monthly_report_planned_items', 'monthly_report_id'],
            ['monthly_report_planned_items', 'year_plan_activity_id'],
            ['monthly_report_off_plan_items', 'monthly_report_id'],
            ['action_logs', 'user_id'],
            ['action_logs', 'period_id'],
            ['action_log_tasks', 'action_log_id'],
            ['weekly_matrices', 'period_id'],
            ['weekly_matrices', 'department_id'],
            ['weekly_matrices', 'created_by'],
            ['weekly_matrices', 'updated_by'],
            ['weekly_matrix_entries', 'weekly_matrix_id'],
            ['weekly_matrix_entry_year_plan_activity', 'weekly_matrix_entry_id'],
            ['weekly_matrix_entry_year_plan_activity', 'year_plan_activity_id'],
            ['action_log_task_assignees', 'action_log_task_id'],
            ['action_log_task_assignees', 'user_id'],
            ['action_log_task_history', 'action_log_task_id'],
            ['action_log_task_history', 'period_id'],
            ['action_log_task_history', 'updated_by'],
            ['nomination_participations', 'nomination_period_id'],
            ['nomination_participations', 'user_id'],
            ['nominations', 'nomination_period_id'],
            ['nominations', 'nominee_id'],
            ['meeting_minutes', 'period_id'],
            ['meeting_minutes', 'uploaded_by'],
            ['official_letters', 'created_by'],
            ['official_letters', 'signatory_id'],
            ['official_letter_user_shares', 'official_letter_id'],
            ['official_letter_user_shares', 'user_id'],
            ['official_letter_user_shares', 'shared_by'],
        ];
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function morphIdColumns(): array
    {
        $permissionTables = config('permission.table_names', []);

        $columns = [
            ['activity_log', 'subject_id'],
            ['activity_log', 'causer_id'],
            ['personal_access_tokens', 'tokenable_id'],
            ['notifications', 'notifiable_id'],
        ];

        if (is_array($permissionTables)) {
            if (! empty($permissionTables['model_has_roles'])) {
                $columns[] = [$permissionTables['model_has_roles'], 'model_id'];
            }
            if (! empty($permissionTables['model_has_permissions'])) {
                $columns[] = [$permissionTables['model_has_permissions'], 'model_id'];
            }
        }

        return $columns;
    }
}
