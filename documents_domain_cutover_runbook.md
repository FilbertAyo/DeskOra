# Documents Domain Cutover Runbook

This runbook is for the direct maintenance-window cutover from legacy `documents` JSON payloads to normalized domain tables.

## 1) Local Dry Run (using restored production dump)

1. Restore DB dump into local MySQL.
2. Deploy this branch locally.
3. Run:

```bash
php artisan migrate --force
php artisan documents:backfill-domain-tables --chunk=200
php artisan documents:verify-domain-parity --sample=25
```

4. Validate key pages manually:
   - Weekly Plan create/edit/view
   - Weekly Report create/edit/view
   - Weekly Budget Review panel + single-item review
   - Action Log index/edit/show

5. Run focused tests:

```bash
php artisan test --filter=WeeklyBudgetReviewTest
php artisan test --filter=ActionLogPanelCorrectionsTest
```

## 2) Pre-Cutover Production Safety Steps

1. Announce maintenance window.
2. Stop cron/scheduler workers for the app.
3. Put app in maintenance mode:

```bash
php artisan down
```

4. Take fresh backup:

```bash
mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "pre_cutover_$(date +%Y%m%d_%H%M%S).sql"
```

5. Snapshot storage (if uploads/files are in use).

## 3) Production Cutover Sequence

1. Deploy this branch.
2. Run migrations:

```bash
php artisan migrate --force
```

3. Run deterministic backfill:

```bash
php artisan documents:backfill-domain-tables --chunk=200
```

4. Run parity verification:

```bash
php artisan documents:verify-domain-parity --sample=25
```

5. Warm caches:

```bash
php artisan optimize:clear
php artisan optimize
```

6. Bring app back:

```bash
php artisan up
```

7. Re-enable cron/scheduler workers.

## 4) Hard Validation Gates

Proceed only if all are true:

- `documents:verify-domain-parity` returns exit code `0`.
- No missing mappings for weekly plans/reports/monthly reports/action logs.
- Weekly budget review routes load and save successfully.
- Action log updates preserve timeline/history behavior.
- No critical errors in application logs after app is up.

## 5) Rollback Procedure

If any gate fails:

1. Keep app in maintenance mode.
2. Checkout known-good commit:

```bash
git checkout 116015368d98416b7b9c56b2ac51e6e7d310fbd5
```

3. Restore pre-cutover DB backup:

```bash
mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < pre_cutover_YYYYMMDD_HHMMSS.sql
```

4. Restore storage snapshot if needed.
5. Clear/warm caches, then bring app up.

## 6) Post-Cutover Monitoring (first 2 hours)

- Watch app logs for SQL and serialization errors.
- Monitor these key pages every 15 minutes:
  - Weekly plans
  - Weekly reports
  - Weekly budget review
  - Action logs
- Re-run:

```bash
php artisan documents:verify-domain-parity --sample=10
```
