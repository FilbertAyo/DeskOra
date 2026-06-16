<?php

namespace App\Console\Commands;

use App\Services\ShortIdMigrator;
use Illuminate\Console\Command;

class ClearStaleSessionsAfterShortIdMigration extends Command
{
    protected $signature = 'app:clear-stale-sessions
                            {--force : Clear sessions even when no legacy ids are detected}';

    protected $description = 'Clear database sessions that still reference pre-migration ULID user ids';

    public function handle(ShortIdMigrator $migrator): int
    {
        if (! $this->option('force') && ! $migrator->sessionsNeedRepair()) {
            $this->info('No stale sessions detected.');

            return self::SUCCESS;
        }

        $count = $migrator->clearStaleSessions();
        $this->info("Cleared {$count} session(s). Users will need to sign in again.");

        return self::SUCCESS;
    }
}
