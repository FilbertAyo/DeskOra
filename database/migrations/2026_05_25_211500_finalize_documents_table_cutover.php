<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillDomainForeignKeys();

        Schema::table('weekly_plan_activities', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_plan_activities', 'carry_over_source_report_id')) {
                $table->dropColumn('carry_over_source_report_id');
            }
        });

        Schema::table('weekly_activity_assignments', function (Blueprint $table) {
            if ($this->hasIndex('weekly_activity_assignments', 'weekly_activity_assignments_source_assignee_uid_unique')) {
                $table->dropUnique('weekly_activity_assignments_source_assignee_uid_unique');
            }

            if (Schema::hasColumn('weekly_activity_assignments', 'source_document_id')) {
                $table->dropColumn('source_document_id');
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if ($this->hasIndex('document_user_shares', 'document_user_shares_document_id_user_id_unique')) {
                $table->dropUnique('document_user_shares_document_id_user_id_unique');
            }

            if (Schema::hasColumn('document_user_shares', 'document_id')) {
                $table->dropColumn('document_id');
            }
        });

        Schema::table('document_share_events', function (Blueprint $table) {
            if ($this->hasIndex('document_share_events', 'document_share_events_document_id_event_type_index')) {
                $table->dropIndex('document_share_events_document_id_event_type_index');
            }

            if (Schema::hasColumn('document_share_events', 'document_id')) {
                $table->dropColumn('document_id');
            }
        });

        Schema::table('weekly_plans', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_plans', 'legacy_document_id')) {
                $table->dropColumn('legacy_document_id');
            }
        });

        Schema::table('weekly_reports', function (Blueprint $table) {
            if (Schema::hasColumn('weekly_reports', 'legacy_document_id')) {
                $table->dropColumn('legacy_document_id');
            }
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            if (Schema::hasColumn('monthly_reports', 'legacy_document_id')) {
                $table->dropColumn('legacy_document_id');
            }
        });

        Schema::table('action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('action_logs', 'legacy_document_id')) {
                $table->dropColumn('legacy_document_id');
            }
        });

        Schema::dropIfExists('documents');
    }

    public function down(): void
    {
        // Destructive cutover migration; rollback requires full restoration
        // from backups/snapshots and prior cutover SQL scripts.
    }

    private function backfillDomainForeignKeys(): void
    {
        if (Schema::hasTable('document_user_shares') && Schema::hasColumn('document_user_shares', 'document_id')) {
            DB::statement('
                UPDATE document_user_shares dus
                INNER JOIN weekly_plans wp ON wp.legacy_document_id = dus.document_id
                SET dus.weekly_plan_id = wp.id
                WHERE dus.weekly_plan_id IS NULL
            ');

            DB::statement('
                UPDATE document_user_shares dus
                INNER JOIN weekly_reports wr ON wr.legacy_document_id = dus.document_id
                SET dus.weekly_report_id = wr.id
                WHERE dus.weekly_report_id IS NULL
            ');
        }

        if (Schema::hasTable('document_share_events') && Schema::hasColumn('document_share_events', 'document_id')) {
            DB::statement('
                UPDATE document_share_events dse
                INNER JOIN weekly_plans wp ON wp.legacy_document_id = dse.document_id
                SET dse.weekly_plan_id = wp.id
                WHERE dse.weekly_plan_id IS NULL
            ');

            DB::statement('
                UPDATE document_share_events dse
                INNER JOIN weekly_reports wr ON wr.legacy_document_id = dse.document_id
                SET dse.weekly_report_id = wr.id
                WHERE dse.weekly_report_id IS NULL
            ');
        }

        if (Schema::hasTable('weekly_activity_assignments') && Schema::hasColumn('weekly_activity_assignments', 'source_document_id')) {
            DB::statement('
                UPDATE weekly_activity_assignments waa
                INNER JOIN weekly_plans wp ON wp.legacy_document_id = waa.source_document_id
                SET waa.source_weekly_plan_id = wp.id
                WHERE waa.source_weekly_plan_id IS NULL
            ');
        }

        if (Schema::hasTable('weekly_plan_activities') && Schema::hasColumn('weekly_plan_activities', 'carry_over_source_report_id')) {
            DB::statement('
                UPDATE weekly_plan_activities wpa
                INNER JOIN weekly_reports wr ON wr.legacy_document_id = wpa.carry_over_source_report_id
                SET wpa.carry_over_source_weekly_report_id = wr.id
                WHERE wpa.carry_over_source_weekly_report_id IS NULL
            ');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }
};

