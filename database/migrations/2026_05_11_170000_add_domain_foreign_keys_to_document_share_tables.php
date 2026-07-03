<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_plans') || ! Schema::hasTable('weekly_reports')) {
            return;
        }

        Schema::table('document_user_shares', function (Blueprint $table) {
            if (! Schema::hasColumn('document_user_shares', 'weekly_plan_id')) {
                $table->foreignUlid('weekly_plan_id')->nullable()->after('document_id')->constrained('weekly_plans')->nullOnDelete();
                $table->unique(['weekly_plan_id', 'user_id'], 'document_user_shares_weekly_plan_user_unique');
                $table->index('weekly_plan_id', 'document_user_shares_weekly_plan_id_index');
            }

            if (! Schema::hasColumn('document_user_shares', 'weekly_report_id')) {
                $table->foreignUlid('weekly_report_id')->nullable()->after('weekly_plan_id')->constrained('weekly_reports')->nullOnDelete();
                $table->unique(['weekly_report_id', 'user_id'], 'document_user_shares_weekly_report_user_unique');
                $table->index('weekly_report_id', 'document_user_shares_weekly_report_id_index');
            }
        });

        Schema::table('document_share_events', function (Blueprint $table) {
            if (! Schema::hasColumn('document_share_events', 'weekly_plan_id')) {
                $table->foreignUlid('weekly_plan_id')->nullable()->after('document_id')->constrained('weekly_plans')->nullOnDelete();
                $table->index(['weekly_plan_id', 'event_type'], 'document_share_events_weekly_plan_event_type_index');
            }

            if (! Schema::hasColumn('document_share_events', 'weekly_report_id')) {
                $table->foreignUlid('weekly_report_id')->nullable()->after('weekly_plan_id')->constrained('weekly_reports')->nullOnDelete();
                $table->index(['weekly_report_id', 'event_type'], 'document_share_events_weekly_report_event_type_index');
            }
        });

        if (Schema::hasTable('weekly_activity_assignments') && Schema::hasColumn('weekly_activity_assignments', 'source_weekly_plan_id')) {
            Schema::table('weekly_activity_assignments', function (Blueprint $table) {
                if (! $this->hasForeignKey('weekly_activity_assignments', 'weekly_activity_assignments_source_weekly_plan_id_foreign')) {
                    $table->foreign('source_weekly_plan_id', 'weekly_activity_assignments_source_weekly_plan_id_foreign')
                        ->references('id')
                        ->on('weekly_plans')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('weekly_plan_activities') && Schema::hasColumn('weekly_plan_activities', 'carry_over_source_weekly_report_id')) {
            Schema::table('weekly_plan_activities', function (Blueprint $table) {
                if (! $this->hasForeignKey('weekly_plan_activities', 'wp_act_carry_wr_fk')) {
                    $table->foreign('carry_over_source_weekly_report_id', 'wp_act_carry_wr_fk')
                        ->references('id')
                        ->on('weekly_reports')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('weekly_plan_activities')) {
            Schema::table('weekly_plan_activities', function (Blueprint $table) {
                if ($this->hasForeignKey('weekly_plan_activities', 'wp_act_carry_wr_fk')) {
                    $table->dropForeign('wp_act_carry_wr_fk');
                }
            });
        }

        if (Schema::hasTable('weekly_activity_assignments')) {
            Schema::table('weekly_activity_assignments', function (Blueprint $table) {
                if ($this->hasForeignKey('weekly_activity_assignments', 'weekly_activity_assignments_source_weekly_plan_id_foreign')) {
                    $table->dropForeign('weekly_activity_assignments_source_weekly_plan_id_foreign');
                }
            });
        }

        Schema::table('document_share_events', function (Blueprint $table) {
            if (Schema::hasColumn('document_share_events', 'weekly_report_id')) {
                $table->dropForeign(['weekly_report_id']);
                $table->dropIndex('document_share_events_weekly_report_event_type_index');
                $table->dropColumn('weekly_report_id');
            }

            if (Schema::hasColumn('document_share_events', 'weekly_plan_id')) {
                $table->dropForeign(['weekly_plan_id']);
                $table->dropIndex('document_share_events_weekly_plan_event_type_index');
                $table->dropColumn('weekly_plan_id');
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if (Schema::hasColumn('document_user_shares', 'weekly_report_id')) {
                $table->dropForeign(['weekly_report_id']);
                $table->dropUnique('document_user_shares_weekly_report_user_unique');
                $table->dropIndex('document_user_shares_weekly_report_id_index');
                $table->dropColumn('weekly_report_id');
            }

            if (Schema::hasColumn('document_user_shares', 'weekly_plan_id')) {
                $table->dropForeign(['weekly_plan_id']);
                $table->dropUnique('document_user_shares_weekly_plan_user_unique');
                $table->dropIndex('document_user_shares_weekly_plan_id_index');
                $table->dropColumn('weekly_plan_id');
            }
        });
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = \Illuminate\Support\Facades\DB::select(
                "SELECT COUNT(*) AS aggregate FROM sqlite_master WHERE type = 'table' AND sql LIKE ?",
                ['%'.$constraint.'%']
            );

            return ((int) ($rows[0]->aggregate ?? 0)) > 0;
        }

        $result = \Illuminate\Support\Facades\DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [$table, $constraint]
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }
};
