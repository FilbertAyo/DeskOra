<?php

namespace App\Console\Commands;

use App\Services\ShortIdMigrator;
use Illuminate\Console\Command;

class MigrateDocumentIdsToShortIds extends Command
{
    protected $signature = 'documents:migrate-ids-to-short
                            {--force : Run even when ids already appear migrated}';

    protected $description = 'Convert document domain primary keys from ULIDs to 4-character ids';

    public function handle(ShortIdMigrator $migrator): int
    {
        if (! $this->option('force') && ! $migrator->needsMigration()) {
            $this->info('Document ids already use the short format. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info('Converting document ids to 4-character format...');
        $migrator->migrate();
        $this->info('Document id migration completed.');

        return self::SUCCESS;
    }
}
