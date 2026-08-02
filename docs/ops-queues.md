# Queue operations (cPanel / production)

WorkHub uses `QUEUE_CONNECTION=database`. Mailables implement `ShouldQueue`.

## How jobs are processed

The Laravel scheduler (installed by `deploy.sh`) runs every minute and includes:

```bash
php artisan queue:work database --stop-when-empty --max-time=50 --tries=3
```

This drains the `jobs` table without a long-running Supervisor process — suitable for shared hosting.

## Optional dedicated worker

If you prefer a persistent worker:

```bash
php artisan queue:work database --sleep=3 --tries=3
```

Run under Supervisor or a persistent screen/tmux session. You may then remove the scheduled `queue:work` entry from `routes/console.php` to avoid overlap.

## Deploy checklist

1. `php artisan migrate --force` creates `jobs` / `failed_jobs` (via `deploy.sh`).
2. Confirm cron runs `artisan schedule:run` every minute.
3. Failed jobs: `php artisan queue:failed` / `queue:retry`.

## Sync fallback

`work-periods:send-reminders --sync` runs reminders inline. When `QUEUE_CONNECTION=sync`, the command always runs inline.
