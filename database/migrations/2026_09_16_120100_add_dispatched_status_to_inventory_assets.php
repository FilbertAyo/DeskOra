<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE inventory_assets MODIFY COLUMN status ENUM(
            'available',
            'assigned',
            'borrowed',
            'dispatched',
            'under_repair',
            'missing',
            'disposed'
        ) NOT NULL DEFAULT 'available'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE inventory_assets MODIFY COLUMN status ENUM(
            'available',
            'assigned',
            'borrowed',
            'under_repair',
            'missing',
            'disposed'
        ) NOT NULL DEFAULT 'available'");
    }
};
