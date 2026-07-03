<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentsUnionView
{
    public const COLLATION = 'utf8mb4_unicode_ci';

    /** @var list<string> */
    public const REQUIRED_TABLES = [
        'weekly_plans',
        'weekly_reports',
        'monthly_reports',
        'action_logs',
    ];

    public static function exists(): bool
    {
        if (Schema::hasTable('documents')) {
            return true;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.views WHERE table_schema = DATABASE() AND table_name = ?',
            ['documents']
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }

    public static function isView(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.views WHERE table_schema = DATABASE() AND table_name = ?',
            ['documents']
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }

    public static function dropIfExists(): void
    {
        DB::statement('DROP VIEW IF EXISTS documents');
        Schema::dropIfExists('documents');
    }

    public static function canCreate(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    public static function shouldDeferAutoEnsure(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];

        foreach (['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe', 'migrate'] as $command) {
            if (in_array($command, $argv, true)) {
                return true;
            }
        }

        return false;
    }

    public static function recreate(bool $withUnicodeCollation = true): void
    {
        DB::statement('DROP VIEW IF EXISTS documents');

        if ($withUnicodeCollation) {
            DB::statement(self::createViewSqlWithCollation());
        } else {
            DB::statement(self::createViewSql());
        }
    }

    public static function createViewSqlWithCollation(): string
    {
        $c = self::COLLATION;
        $col = static fn (string $expression): string => "({$expression} COLLATE {$c})";
        $lit = static fn (string $value): string => "('{$value}' COLLATE {$c})";

        return '
            CREATE VIEW documents AS
            SELECT
                '.$col('wp.id').' AS id,
                '.$col('wp.user_id').' AS user_id,
                '.$col('wp.period_id').' AS period_id,
                '.$lit('weekly_plan').' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                '.$col('wp.title').' AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                '.$col('wp.main_target').' AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                '.$col('wp.state').' AS state,
                '.$col('wp.review_rejection_reason').' AS review_rejection_reason,
                '.$col('wp.budget_review_status').' AS budget_review_status,
                '.$col('wp.budget_reviewed_by').' AS budget_reviewed_by,
                wp.budget_reviewed_at AS budget_reviewed_at,
                wp.budget_provided_amount AS budget_provided_amount,
                '.$col('wp.budget_review_note').' AS budget_review_note,
                NULL AS budget_review_items,
                wp.created_at AS created_at,
                wp.updated_at AS updated_at,
                wp.deleted_at AS deleted_at
            FROM weekly_plans wp
            UNION ALL
            SELECT
                '.$col('wr.id').' AS id,
                '.$col('wr.user_id').' AS user_id,
                '.$col('wr.period_id').' AS period_id,
                '.$lit('weekly_report').' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                '.$col('wr.title').' AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                NULL AS main_target,
                wr.target_accomplished AS target_accomplished,
                '.$col('wr.target_explanation').' AS target_explanation,
                '.$col('wr.state').' AS state,
                '.$col('wr.review_rejection_reason').' AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                wr.created_at AS created_at,
                wr.updated_at AS updated_at,
                wr.deleted_at AS deleted_at
            FROM weekly_reports wr
            UNION ALL
            SELECT
                '.$col('mr.id').' AS id,
                '.$col('mr.user_id').' AS user_id,
                NULL AS period_id,
                '.$lit('monthly_report').' AS type,
                '.$col('mr.file_path').' AS file_path,
                '.$col('mr.attachment').' AS attachment,
                mr.month AS month,
                mr.year AS year,
                '.$col('mr.title').' AS title,
                mr.file_size AS file_size,
                '.$col('mr.original_filename').' AS original_filename,
                NULL AS content,
                NULL AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                '.$col('mr.state').' AS state,
                NULL AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                mr.created_at AS created_at,
                mr.updated_at AS updated_at,
                mr.deleted_at AS deleted_at
            FROM monthly_reports mr
            UNION ALL
            SELECT
                '.$col('al.id').' AS id,
                '.$col('al.user_id').' AS user_id,
                '.$col('al.period_id').' AS period_id,
                '.$lit('action_log').' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                '.$col('al.title').' AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                NULL AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                '.$col('al.state').' AS state,
                NULL AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                al.created_at AS created_at,
                al.updated_at AS updated_at,
                al.deleted_at AS deleted_at
            FROM action_logs al
        ';
    }

    public static function createViewSql(): string
    {
        return "
            CREATE VIEW documents AS
            SELECT
                wp.id AS id,
                wp.user_id AS user_id,
                wp.period_id AS period_id,
                'weekly_plan' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                wp.title AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                wp.main_target AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                wp.state AS state,
                wp.review_rejection_reason AS review_rejection_reason,
                wp.budget_review_status AS budget_review_status,
                wp.budget_reviewed_by AS budget_reviewed_by,
                wp.budget_reviewed_at AS budget_reviewed_at,
                wp.budget_provided_amount AS budget_provided_amount,
                wp.budget_review_note AS budget_review_note,
                NULL AS budget_review_items,
                wp.created_at AS created_at,
                wp.updated_at AS updated_at,
                wp.deleted_at AS deleted_at
            FROM weekly_plans wp
            UNION ALL
            SELECT
                wr.id AS id,
                wr.user_id AS user_id,
                wr.period_id AS period_id,
                'weekly_report' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                wr.title AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                NULL AS main_target,
                wr.target_accomplished AS target_accomplished,
                wr.target_explanation AS target_explanation,
                wr.state AS state,
                wr.review_rejection_reason AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                wr.created_at AS created_at,
                wr.updated_at AS updated_at,
                wr.deleted_at AS deleted_at
            FROM weekly_reports wr
            UNION ALL
            SELECT
                mr.id AS id,
                mr.user_id AS user_id,
                NULL AS period_id,
                'monthly_report' AS type,
                mr.file_path AS file_path,
                mr.attachment AS attachment,
                mr.month AS month,
                mr.year AS year,
                mr.title AS title,
                mr.file_size AS file_size,
                mr.original_filename AS original_filename,
                NULL AS content,
                NULL AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                mr.state AS state,
                NULL AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                mr.created_at AS created_at,
                mr.updated_at AS updated_at,
                mr.deleted_at AS deleted_at
            FROM monthly_reports mr
            UNION ALL
            SELECT
                al.id AS id,
                al.user_id AS user_id,
                al.period_id AS period_id,
                'action_log' AS type,
                NULL AS file_path,
                NULL AS attachment,
                NULL AS month,
                NULL AS year,
                al.title AS title,
                NULL AS file_size,
                NULL AS original_filename,
                NULL AS content,
                NULL AS main_target,
                NULL AS target_accomplished,
                NULL AS target_explanation,
                al.state AS state,
                NULL AS review_rejection_reason,
                NULL AS budget_review_status,
                NULL AS budget_reviewed_by,
                NULL AS budget_reviewed_at,
                NULL AS budget_provided_amount,
                NULL AS budget_review_note,
                NULL AS budget_review_items,
                al.created_at AS created_at,
                al.updated_at AS updated_at,
                al.deleted_at AS deleted_at
            FROM action_logs al
        ";
    }
}
