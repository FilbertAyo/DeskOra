<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_user_shares', 'weekly_plan_id')) {
            Schema::table('document_user_shares', function (Blueprint $table) {
                $table->ulid('weekly_plan_id')->nullable()->after('document_id');
                $table->foreign('weekly_plan_id', 'doc_user_shares_wp_fk')
                    ->references('id')
                    ->on('weekly_plans')
                    ->nullOnDelete();
            });
        }

        Schema::table('document_user_shares', function (Blueprint $table) {
            if (! $this->hasIndex('document_user_shares', 'document_user_shares_weekly_plan_user_unique')) {
                $table->unique(['weekly_plan_id', 'user_id'], 'document_user_shares_weekly_plan_user_unique');
            }
            if (! $this->hasIndex('document_user_shares', 'document_user_shares_weekly_plan_id_index')) {
                $table->index('weekly_plan_id', 'document_user_shares_weekly_plan_id_index');
            }
        });

        if (! Schema::hasColumn('document_share_events', 'weekly_plan_id')) {
            Schema::table('document_share_events', function (Blueprint $table) {
                $table->ulid('weekly_plan_id')->nullable()->after('document_id');
                $table->foreign('weekly_plan_id', 'doc_share_events_wp_fk')
                    ->references('id')
                    ->on('weekly_plans')
                    ->nullOnDelete();
            });
        }

        Schema::table('document_share_events', function (Blueprint $table) {
            if (! $this->hasIndex('document_share_events', 'document_share_events_weekly_plan_event_type_index')) {
                $table->index(['weekly_plan_id', 'event_type'], 'document_share_events_weekly_plan_event_type_index');
            }
        });

        if (! Schema::hasColumn('weekly_activity_assignments', 'source_weekly_plan_id')) {
            Schema::table('weekly_activity_assignments', function (Blueprint $table) {
                $table->ulid('source_weekly_plan_id')->nullable()->after('source_document_id');
                $table->foreign('source_weekly_plan_id', 'weekly_assignments_source_wp_fk')
                    ->references('id')
                    ->on('weekly_plans')
                    ->nullOnDelete();
            });
        }

        Schema::table('weekly_activity_assignments', function (Blueprint $table) {
            if (! $this->hasIndex('weekly_activity_assignments', 'weekly_activity_assignments_plan_assignee_uid_unique')) {
                $table->unique(
                    ['source_weekly_plan_id', 'assignee_user_id', 'activity_uid'],
                    'weekly_activity_assignments_plan_assignee_uid_unique'
                );
            }
            if (! $this->hasIndex('weekly_activity_assignments', 'weekly_activity_assignments_plan_period_index')) {
                $table->index(
                    ['source_weekly_plan_id', 'period_id'],
                    'weekly_activity_assignments_plan_period_index'
                );
            }
        });

        if (! Schema::hasColumn('weekly_plan_activities', 'carry_over_source_weekly_report_id')) {
            Schema::table('weekly_plan_activities', function (Blueprint $table) {
                $table->ulid('carry_over_source_weekly_report_id')->nullable()->after('carry_over_source_report_id');
                $table->foreign('carry_over_source_weekly_report_id', 'wp_activities_carry_over_wr_fk')
                    ->references('id')
                    ->on('weekly_reports')
                    ->nullOnDelete();
            });
        }

        Schema::table('weekly_plan_activities', function (Blueprint $table) {
            if (! $this->hasIndex('weekly_plan_activities', 'weekly_plan_activities_carry_over_weekly_report_index')) {
                $table->index(
                    'carry_over_source_weekly_report_id',
                    'weekly_plan_activities_carry_over_weekly_report_index'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plan_activities', function (Blueprint $table) {
            if ($this->hasIndex('weekly_plan_activities', 'weekly_plan_activities_carry_over_weekly_report_index')) {
                $table->dropIndex('weekly_plan_activities_carry_over_weekly_report_index');
            }
            if ($this->hasForeignKey('weekly_plan_activities', 'wp_activities_carry_over_wr_fk')) {
                $table->dropForeign('wp_activities_carry_over_wr_fk');
            }
            if (Schema::hasColumn('weekly_plan_activities', 'carry_over_source_weekly_report_id')) {
                $table->dropColumn('carry_over_source_weekly_report_id');
            }
        });

        Schema::table('weekly_activity_assignments', function (Blueprint $table) {
            if ($this->hasIndex('weekly_activity_assignments', 'weekly_activity_assignments_plan_period_index')) {
                $table->dropIndex('weekly_activity_assignments_plan_period_index');
            }
            if ($this->hasIndex('weekly_activity_assignments', 'weekly_activity_assignments_plan_assignee_uid_unique')) {
                $table->dropUnique('weekly_activity_assignments_plan_assignee_uid_unique');
            }
            if ($this->hasForeignKey('weekly_activity_assignments', 'weekly_assignments_source_wp_fk')) {
                $table->dropForeign('weekly_assignments_source_wp_fk');
            }
            if (Schema::hasColumn('weekly_activity_assignments', 'source_weekly_plan_id')) {
                $table->dropColumn('source_weekly_plan_id');
            }
        });

        Schema::table('document_share_events', function (Blueprint $table) {
            if ($this->hasIndex('document_share_events', 'document_share_events_weekly_plan_event_type_index')) {
                $table->dropIndex('document_share_events_weekly_plan_event_type_index');
            }
            if ($this->hasForeignKey('document_share_events', 'doc_share_events_wp_fk')) {
                $table->dropForeign('doc_share_events_wp_fk');
            }
            if (Schema::hasColumn('document_share_events', 'weekly_plan_id')) {
                $table->dropColumn('weekly_plan_id');
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if ($this->hasIndex('document_user_shares', 'document_user_shares_weekly_plan_id_index')) {
                $table->dropIndex('document_user_shares_weekly_plan_id_index');
            }
            if ($this->hasIndex('document_user_shares', 'document_user_shares_weekly_plan_user_unique')) {
                $table->dropUnique('document_user_shares_weekly_plan_user_unique');
            }
            if ($this->hasForeignKey('document_user_shares', 'doc_user_shares_wp_fk')) {
                $table->dropForeign('doc_user_shares_wp_fk');
            }
            if (Schema::hasColumn('document_user_shares', 'weekly_plan_id')) {
                $table->dropColumn('weekly_plan_id');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
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
