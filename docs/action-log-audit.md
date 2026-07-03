# Action Log System Audit

Last updated: 2026-07-03

## Architecture

Action logs use the **documents union view** pattern: `action_logs.id` is the document ID. Task data lives in normalized tables (`action_log_tasks`, `action_log_task_assignees`, `action_log_task_history`) synced from assembled `Document::content` on every save.

Cross-week timelines aggregate history from multiple weekly logs by `task_uid` (show page, PDF, staff dashboard).

## Schema

| Table | Purpose |
|-------|---------|
| `action_logs` | One row per weekly log; `state`, `submission_email_sent`, `removed_task_uids`, `superseded_by_action_log_id` |
| `action_log_tasks` | Per-week task snapshot; unique `(action_log_id, task_uid)` |
| `action_log_task_assignees` | Many-to-many staff assignment |
| `action_log_task_history` | Progress/reassign audit trail |

## Known issues (addressed in phased rollout)

1. GET requests mutated data (edit page, dashboard) — **fixed**: carry-forward only on explicit writes
2. Reassign metadata lost on reload — **fixed**: `reassigned_from_user_ids` / `reassigned_to_user_ids` on history
3. Deleted tasks restored by carry-forward — **fixed**: `removed_task_uids` blocklist
4. Duplicate logs per `period_id` — **fixed**: audit command + superseded_by merge
5. Performance: load all logs on dashboard — **fixed**: scoped queries + SQL timeline service
6. Full task delete/recreate on sync — **fixed**: incremental upsert by `task_uid`

## Operational commands

```bash
# Check migration status
php artisan migrate:status

# Audit duplicate action logs per work period (dry-run default)
php artisan action-logs:audit-duplicates

# Merge duplicates into canonical log (requires --apply)
php artisan action-logs:audit-duplicates --apply

# Sync carried tasks from prior weeks (explicit)
php artisan action-logs:sync-carried-tasks

# Dry-run carry sync
php artisan action-logs:sync-carried-tasks --dry-run
```

## Baseline queries (production copy)

```sql
-- Duplicate logs per period
SELECT period_id, COUNT(*) AS cnt
FROM action_logs
WHERE deleted_at IS NULL AND superseded_by_action_log_id IS NULL
GROUP BY period_id HAVING cnt > 1;

-- Row counts
SELECT 'action_logs' AS t, COUNT(*) FROM action_logs
UNION ALL SELECT 'action_log_tasks', COUNT(*) FROM action_log_tasks
UNION ALL SELECT 'action_log_task_history', COUNT(*) FROM action_log_task_history
UNION ALL SELECT 'action_log_task_assignees', COUNT(*) FROM action_log_task_assignees;
```

## Deployment order

1. `php artisan migrate` (reassign columns, indexes, superseded_by)
2. `php artisan action-logs:audit-duplicates` — review output
3. `php artisan action-logs:audit-duplicates --apply` if duplicates exist
4. Deploy application code
5. Run `php artisan test --filter=ActionLog`

## Rollback

Migrations include `down()` methods. Superseded logs remain in DB (soft-linked, not hard-deleted). `removed_task_uids` and history data are never dropped by migrations.
