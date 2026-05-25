<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            if ($this->hasForeignKey('weekly_plans', 'weekly_plans_legacy_document_id_foreign')) {
                $table->dropForeign('weekly_plans_legacy_document_id_foreign');
            }
        });

        Schema::table('weekly_reports', function (Blueprint $table) {
            if ($this->hasForeignKey('weekly_reports', 'weekly_reports_legacy_document_id_foreign')) {
                $table->dropForeign('weekly_reports_legacy_document_id_foreign');
            }
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            if ($this->hasForeignKey('monthly_reports', 'monthly_reports_legacy_document_id_foreign')) {
                $table->dropForeign('monthly_reports_legacy_document_id_foreign');
            }
        });

        Schema::table('action_logs', function (Blueprint $table) {
            if ($this->hasForeignKey('action_logs', 'action_logs_legacy_document_id_foreign')) {
                $table->dropForeign('action_logs_legacy_document_id_foreign');
            }
        });

        Schema::table('weekly_plan_activities', function (Blueprint $table) {
            if ($this->hasForeignKey('weekly_plan_activities', 'weekly_plan_activities_carry_over_source_report_id_foreign')) {
                $table->dropForeign('weekly_plan_activities_carry_over_source_report_id_foreign');
            }
        });

        Schema::table('weekly_activity_assignments', function (Blueprint $table) {
            if ($this->hasForeignKey('weekly_activity_assignments', 'weekly_activity_assignments_source_document_id_foreign')) {
                $table->dropForeign('weekly_activity_assignments_source_document_id_foreign');
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if ($this->hasForeignKey('document_user_shares', 'document_user_shares_document_id_foreign')) {
                $table->dropForeign('document_user_shares_document_id_foreign');
            }
        });

        Schema::table('document_share_events', function (Blueprint $table) {
            if ($this->hasForeignKey('document_share_events', 'document_share_events_document_id_foreign')) {
                $table->dropForeign('document_share_events_document_id_foreign');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            if (! $this->hasForeignKey('weekly_plans', 'weekly_plans_legacy_document_id_foreign')) {
                $table->foreign('legacy_document_id', 'weekly_plans_legacy_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('weekly_reports', function (Blueprint $table) {
            if (! $this->hasForeignKey('weekly_reports', 'weekly_reports_legacy_document_id_foreign')) {
                $table->foreign('legacy_document_id', 'weekly_reports_legacy_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('monthly_reports', function (Blueprint $table) {
            if (! $this->hasForeignKey('monthly_reports', 'monthly_reports_legacy_document_id_foreign')) {
                $table->foreign('legacy_document_id', 'monthly_reports_legacy_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('action_logs', function (Blueprint $table) {
            if (! $this->hasForeignKey('action_logs', 'action_logs_legacy_document_id_foreign')) {
                $table->foreign('legacy_document_id', 'action_logs_legacy_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('weekly_plan_activities', function (Blueprint $table) {
            if (! $this->hasForeignKey('weekly_plan_activities', 'weekly_plan_activities_carry_over_source_report_id_foreign')) {
                $table->foreign('carry_over_source_report_id', 'weekly_plan_activities_carry_over_source_report_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->nullOnDelete();
            }
        });

        Schema::table('weekly_activity_assignments', function (Blueprint $table) {
            if (! $this->hasForeignKey('weekly_activity_assignments', 'weekly_activity_assignments_source_document_id_foreign')) {
                $table->foreign('source_document_id', 'weekly_activity_assignments_source_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if (! $this->hasForeignKey('document_user_shares', 'document_user_shares_document_id_foreign')) {
                $table->foreign('document_id', 'document_user_shares_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });

        Schema::table('document_share_events', function (Blueprint $table) {
            if (! $this->hasForeignKey('document_share_events', 'document_share_events_document_id_foreign')) {
                $table->foreign('document_id', 'document_share_events_document_id_foreign')
                    ->references('id')
                    ->on('documents')
                    ->cascadeOnDelete();
            }
        });
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$table, $constraint]
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }
};
