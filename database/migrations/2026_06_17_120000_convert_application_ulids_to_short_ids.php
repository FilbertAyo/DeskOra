<?php

use App\Services\ShortIdMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(ShortIdMigrator::class)->migrate();
    }

    public function down(): void
    {
        // Destructive id conversion; rollback requires restoring from backup.
    }
};
