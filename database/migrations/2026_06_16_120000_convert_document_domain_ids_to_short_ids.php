<?php

use App\Services\Documents\DocumentShortIdMigrator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(DocumentShortIdMigrator::class)->migrate();
    }

    public function down(): void
    {
        // Destructive id conversion; rollback requires restoring from backup.
    }
};
