<?php

use App\Services\ShortIdMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $migrator = app(ShortIdMigrator::class);

        if ($migrator->sessionsNeedRepair()) {
            $migrator->clearStaleSessions();
        }
    }

    public function down(): void
    {
        //
    }
};
