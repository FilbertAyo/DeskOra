<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('document_user_shares', 'weekly_report_id')) {
            Schema::table('document_user_shares', function (Blueprint $table) {
                $table->ulid('weekly_report_id')->nullable()->after('weekly_plan_id');
                $table->foreign('weekly_report_id', 'doc_user_shares_wr_fk')
                    ->references('id')
                    ->on('weekly_reports')
                    ->nullOnDelete();
            });
        }

        Schema::table('document_user_shares', function (Blueprint $table) {
            if (! $this->hasIndex('document_user_shares', 'document_user_shares_weekly_report_user_unique')) {
                $table->unique(['weekly_report_id', 'user_id'], 'document_user_shares_weekly_report_user_unique');
            }
            if (! $this->hasIndex('document_user_shares', 'document_user_shares_weekly_report_id_index')) {
                $table->index('weekly_report_id', 'document_user_shares_weekly_report_id_index');
            }
        });

        if (! Schema::hasColumn('document_share_events', 'weekly_report_id')) {
            Schema::table('document_share_events', function (Blueprint $table) {
                $table->ulid('weekly_report_id')->nullable()->after('weekly_plan_id');
                $table->foreign('weekly_report_id', 'doc_share_events_wr_fk')
                    ->references('id')
                    ->on('weekly_reports')
                    ->nullOnDelete();
            });
        }

        Schema::table('document_share_events', function (Blueprint $table) {
            if (! $this->hasIndex('document_share_events', 'document_share_events_weekly_report_event_type_index')) {
                $table->index(['weekly_report_id', 'event_type'], 'document_share_events_weekly_report_event_type_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_share_events', function (Blueprint $table) {
            if ($this->hasIndex('document_share_events', 'document_share_events_weekly_report_event_type_index')) {
                $table->dropIndex('document_share_events_weekly_report_event_type_index');
            }
            if ($this->hasForeignKey('document_share_events', 'doc_share_events_wr_fk')) {
                $table->dropForeign('doc_share_events_wr_fk');
            }
            if (Schema::hasColumn('document_share_events', 'weekly_report_id')) {
                $table->dropColumn('weekly_report_id');
            }
        });

        Schema::table('document_user_shares', function (Blueprint $table) {
            if ($this->hasIndex('document_user_shares', 'document_user_shares_weekly_report_id_index')) {
                $table->dropIndex('document_user_shares_weekly_report_id_index');
            }
            if ($this->hasIndex('document_user_shares', 'document_user_shares_weekly_report_user_unique')) {
                $table->dropUnique('document_user_shares_weekly_report_user_unique');
            }
            if ($this->hasForeignKey('document_user_shares', 'doc_user_shares_wr_fk')) {
                $table->dropForeign('doc_user_shares_wr_fk');
            }
            if (Schema::hasColumn('document_user_shares', 'weekly_report_id')) {
                $table->dropColumn('weekly_report_id');
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
