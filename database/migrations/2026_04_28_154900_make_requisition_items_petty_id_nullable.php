<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite does not support MySQL's `ALTER TABLE ... MODIFY` syntax.
            return;
        }

        if (! Schema::hasTable('requisition_items') || ! Schema::hasColumn('requisition_items', 'petty_id')) {
            return;
        }

        // Compatibility patch for environments where requisition_items was created
        // before petty_id became nullable in the unified request refactor.
        DB::statement('ALTER TABLE requisition_items MODIFY petty_id CHAR(26) NULL');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('requisition_items') || ! Schema::hasColumn('requisition_items', 'petty_id')) {
            return;
        }

        DB::statement('ALTER TABLE requisition_items MODIFY petty_id CHAR(26) NOT NULL');
    }
};
