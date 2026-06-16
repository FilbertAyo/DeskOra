<?php

namespace App\Console\Commands;

use App\Services\ShortIdMigrator;
use Illuminate\Console\Command;

class MigrateShortIds extends Command
{
    protected $signature = 'app:migrate-short-ids
                            {--force : Run even when ids already appear migrated}';

    protected $description = 'Convert application ULID primary keys to 4-character ids';

    public function handle(ShortIdMigrator $migrator): int
    {
        if (! $this->option('force') && ! $migrator->needsMigration()) {
            $this->info('Application ids already use the short format. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Converting application ids to 4-character format...');
        $migrator->migrate();
        $this->info('Short id migration completed.');

        return self::SUCCESS;
    }
}
