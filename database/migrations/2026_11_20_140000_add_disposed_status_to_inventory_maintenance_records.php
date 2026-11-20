<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_maintenance_records MODIFY COLUMN status ENUM(
                'reported',
                'completed',
                'disposed'
            ) NOT NULL DEFAULT 'reported'");

            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('inventory_maintenance_records', function (Blueprint $table) {
                $table->string('status', 32)->default('reported')->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE inventory_maintenance_records MODIFY COLUMN status ENUM(
                'reported',
                'completed'
            ) NOT NULL DEFAULT 'reported'");

            return;
        }

        if ($driver === 'sqlite') {
            Schema::table('inventory_maintenance_records', function (Blueprint $table) {
                $table->string('status', 32)->default('reported')->change();
            });
        }
    }
};
