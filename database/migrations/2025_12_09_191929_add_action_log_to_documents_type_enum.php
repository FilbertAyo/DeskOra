<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, check if enum type exists and add value
        // First, check what database driver we're using
        $driver = DB::getDriverName();
        
        if ($driver === 'pgsql') {
            // PostgreSQL: Check if enum type exists before trying to add value
            // Note: PostgreSQL enum type name is auto-generated as {table}_{column}_enum
            $enumExists = DB::selectOne("
                SELECT EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'documents_type_enum'
                ) as exists
            ");
            
            if ($enumExists && $enumExists->exists) {
                // Enum type exists, check if value already exists
                $valueExists = DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1 FROM pg_enum 
                        WHERE enumlabel = 'action_log' 
                        AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'documents_type_enum')
                    ) as exists
                ");
                
                if (!$valueExists || !$valueExists->exists) {
                    // Value doesn't exist, add it
                    DB::statement("ALTER TYPE documents_type_enum ADD VALUE 'action_log'");
                }
            }
            // If enum doesn't exist, it means the table was created with action_log already included, so no-op
        } else {
            // MySQL/MariaDB: Modify column enum only if action_log is not already in the enum
            $currentEnum = DB::selectOne("
                SELECT COLUMN_TYPE 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'documents' 
                AND COLUMN_NAME = 'type'
            ");
            
            if ($currentEnum && strpos($currentEnum->COLUMN_TYPE, 'action_log') === false) {
                DB::statement("ALTER TABLE documents MODIFY COLUMN type ENUM('weekly_plan', 'weekly_report', 'monthly_report', 'action_log')");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: PostgreSQL doesn't support removing enum values directly
        // MySQL/MariaDB: Revert back to original enum values
        $driver = DB::getDriverName();
        
        if ($driver !== 'pgsql') {
            DB::statement("ALTER TABLE documents MODIFY COLUMN type ENUM('weekly_plan', 'weekly_report', 'monthly_report')");
        }
        // PostgreSQL: No-op (can't remove enum values easily)
    }
};
