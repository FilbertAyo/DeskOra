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

        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'rejected',
            'processing',
            'resubmission',
            'resubmitted',
            'paid',
            'draft'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE requests MODIFY COLUMN status ENUM(
            'pending',
            'approved',
            'rejected',
            'processing',
            'resubmission',
            'resubmitted',
            'paid'
        ) NOT NULL DEFAULT 'pending'");
    }
};
