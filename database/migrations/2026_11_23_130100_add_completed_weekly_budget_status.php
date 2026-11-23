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

        if ($driver === 'sqlite') {
            $this->relaxSqliteEnum('weekly_plans', 'budget_review_status', nullable: true);
            $this->relaxSqliteEnum('weekly_plan_budget_reviews', 'approval_stage', nullable: false, default: 'pending');

            return;
        }

        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE weekly_plans MODIFY COLUMN budget_review_status ENUM(
            'pending',
            'approved',
            'rejected',
            'completed'
        ) NULL DEFAULT NULL");

        DB::statement("ALTER TABLE weekly_plan_budget_reviews MODIFY COLUMN approval_stage ENUM(
            'pending',
            'reviewed',
            'approved',
            'authorized',
            'rejected',
            'completed'
        ) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE weekly_plans SET budget_review_status = 'approved' WHERE budget_review_status = 'completed'");
        DB::statement("UPDATE weekly_plan_budget_reviews SET approval_stage = 'authorized' WHERE approval_stage = 'completed'");

        DB::statement("ALTER TABLE weekly_plans MODIFY COLUMN budget_review_status ENUM(
            'pending',
            'approved',
            'rejected'
        ) NULL DEFAULT NULL");

        DB::statement("ALTER TABLE weekly_plan_budget_reviews MODIFY COLUMN approval_stage ENUM(
            'pending',
            'reviewed',
            'approved',
            'authorized',
            'rejected'
        ) NOT NULL DEFAULT 'pending'");
    }

    private function relaxSqliteEnum(string $table, string $column, bool $nullable, ?string $default = null): void
    {
        DB::statement('DROP INDEX IF EXISTS '.$table.'_'.$column.'_index');

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });

        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable, $default) {
            $definition = $blueprint->string($column, 32);
            if ($nullable) {
                $definition->nullable();
            }
            if ($default !== null) {
                $definition->default($default);
            }
            $definition->index();
        });
    }
};
