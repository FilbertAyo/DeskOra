<?php

namespace App\Services;

use App\Database\DocumentsUnionView;
use App\Support\ShortId;
use App\Support\ShortIdSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ShortIdMigrator
{
    public function __construct(
        private readonly ShortIdGenerator $generator,
    ) {}

    public function needsMigration(): bool
    {
        foreach (ShortIdSchema::primaryKeyTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $sampleId = DB::table($table)->value('id');
            if ($sampleId === null) {
                continue;
            }

            if (! ShortId::is((string) $sampleId)) {
                return true;
            }
        }

        return false;
    }

    public function migrate(): void
    {
        $this->cleanupLegacyDocumentForeignKeys();

        $shouldConvert = $this->needsMigration();

        if ($shouldConvert) {
            $mapping = $this->buildMapping();

            if ($mapping !== []) {
                $this->dropDocumentsView();
                $this->disableForeignKeys();

                try {
                    $this->updateForeignKeyColumns($mapping);
                    $this->updateSessionPayloads($mapping);
                    $this->updateMorphIdColumns($mapping);
                    $this->updatePrimaryKeys($mapping);
                    $this->updateWeeklyDepartmentMatrixEntries($mapping);
                } finally {
                    $this->enableForeignKeys();
                }

                $this->clearStaleSessions();
            }
        } elseif ($this->sessionsNeedRepair()) {
            $this->clearStaleSessions();
        }

        if ($shouldConvert || ! DocumentsUnionView::exists()) {
            $this->recreateDocumentsView();
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildMapping(): array
    {
        /** @var array<string, string> $mapping */
        $mapping = [];
        /** @var array<string, true> $reserved */
        $reserved = [];

        foreach (ShortIdSchema::primaryKeyTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->pluck('id') as $oldId) {
                $oldId = (string) $oldId;

                if (ShortId::is($oldId)) {
                    $mapping[$oldId] = $oldId;
                    $reserved[$oldId] = true;

                    continue;
                }

                $newId = $this->generator->generateUnique($reserved);
                $mapping[$oldId] = $newId;
                $reserved[$newId] = true;
            }
        }

        return $mapping;
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function updateForeignKeyColumns(array $mapping): void
    {
        foreach (ShortIdSchema::foreignKeyColumns() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($mapping as $oldId => $newId) {
                if ($oldId === $newId) {
                    continue;
                }

                DB::table($table)
                    ->where($column, $oldId)
                    ->update([$column => $newId]);
            }
        }
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function updateSessionPayloads(array $mapping): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        foreach (DB::table('sessions')->cursor() as $session) {
            $payload = (string) ($session->payload ?? '');
            if ($payload === '') {
                continue;
            }

            $decoded = @unserialize(base64_decode($payload, true));
            if (! is_array($decoded)) {
                $decoded = @unserialize($payload);
            }

            if (! is_array($decoded)) {
                continue;
            }

            $updated = $this->replaceMappedIdsInValue($decoded, $mapping);
            $updates = [];

            if ($updated !== $decoded) {
                $updates['payload'] = base64_encode(serialize($updated));
            }

            $userId = $session->user_id;
            if (is_string($userId) && isset($mapping[$userId])) {
                $updates['user_id'] = $mapping[$userId];
            }

            if ($updates !== []) {
                DB::table('sessions')->where('id', $session->id)->update($updates);
            }
        }
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function replaceMappedIdsInValue(mixed $value, array $mapping): mixed
    {
        if (is_string($value) && isset($mapping[$value])) {
            return $mapping[$value];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $newKey = is_string($key) && isset($mapping[$key]) ? $mapping[$key] : $key;
                $result[$newKey] = $this->replaceMappedIdsInValue($item, $mapping);
            }

            return $result;
        }

        return $value;
    }

    public function sessionsNeedRepair(): bool
    {
        if (! Schema::hasTable('sessions')) {
            return false;
        }

        foreach (DB::table('sessions')->select(['user_id', 'payload'])->cursor() as $session) {
            $userId = $session->user_id;
            if (is_string($userId) && $userId !== '' && ! ShortId::is($userId)) {
                return true;
            }

            $payload = (string) ($session->payload ?? '');
            if ($payload !== '' && $this->payloadContainsLegacyId($payload)) {
                return true;
            }
        }

        return false;
    }

    public function clearStaleSessions(): int
    {
        if (! Schema::hasTable('sessions')) {
            return 0;
        }

        $count = (int) DB::table('sessions')->count();
        DB::table('sessions')->delete();

        return $count;
    }

    private function payloadContainsLegacyId(string $payload): bool
    {
        if (preg_match('/[0-9a-z]{26}/', $payload) === 1) {
            return true;
        }

        $decoded = @unserialize(base64_decode($payload, true));
        if (! is_array($decoded)) {
            $decoded = @unserialize($payload);
        }

        if (! is_array($decoded)) {
            return false;
        }

        return $this->arrayContainsLegacyId($decoded);
    }

    private function arrayContainsLegacyId(mixed $value): bool
    {
        if (is_string($value) && ShortId::isLegacyUlid($value)) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->arrayContainsLegacyId($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function updateMorphIdColumns(array $mapping): void
    {
        foreach (ShortIdSchema::morphIdColumns() as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($mapping as $oldId => $newId) {
                if ($oldId === $newId) {
                    continue;
                }

                DB::table($table)
                    ->where($column, $oldId)
                    ->update([$column => $newId]);
            }
        }
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function updatePrimaryKeys(array $mapping): void
    {
        foreach ($mapping as $oldId => $newId) {
            if ($oldId === $newId) {
                continue;
            }

            foreach (ShortIdSchema::primaryKeyTables() as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $updated = DB::table($table)->where('id', $oldId)->update(['id' => $newId]);
                if ($updated > 0) {
                    break;
                }
            }
        }
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function updateWeeklyDepartmentMatrixEntries(array $mapping): void
    {
        if (! Schema::hasTable('weekly_department_matrices')) {
            return;
        }

        $rows = DB::table('weekly_department_matrices')->select(['id', 'entries'])->get();

        foreach ($rows as $row) {
            $entries = json_decode((string) $row->entries, true);
            if (! is_array($entries)) {
                continue;
            }

            $changed = false;
            foreach ($entries as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                foreach (['source_document_id', 'source_weekly_plan_id', 'impact_year_plan_activity_ids'] as $field) {
                    if ($field === 'impact_year_plan_activity_ids' && isset($entry[$field]) && is_array($entry[$field])) {
                        foreach ($entry[$field] as $activityIndex => $activityId) {
                            $current = (string) $activityId;
                            if ($current !== '' && isset($mapping[$current]) && $mapping[$current] !== $current) {
                                $entries[$index][$field][$activityIndex] = $mapping[$current];
                                $changed = true;
                            }
                        }

                        continue;
                    }

                    if (! array_key_exists($field, $entry)) {
                        continue;
                    }

                    $current = (string) ($entry[$field] ?? '');
                    if ($current !== '' && isset($mapping[$current]) && $mapping[$current] !== $current) {
                        $entries[$index][$field] = $mapping[$current];
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                DB::table('weekly_department_matrices')
                    ->where('id', $row->id)
                    ->update(['entries' => json_encode($entries)]);
            }
        }
    }

    private function dropDocumentsView(): void
    {
        if (DocumentsUnionView::exists()) {
            DB::statement('DROP VIEW IF EXISTS documents');
        }
    }

    private function recreateDocumentsView(): void
    {
        if (! Schema::hasTable('weekly_plans')) {
            return;
        }

        DocumentsUnionView::recreate(DB::connection()->getDriverName() === 'mysql');
    }

    private function cleanupLegacyDocumentForeignKeys(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $this->disableForeignKeys();

        try {
            if (Schema::hasTable('document_user_shares') && Schema::hasColumn('document_user_shares', 'document_id')) {
                Schema::drop('document_user_shares');
                Schema::create('document_user_shares', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignUlid('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
                    $table->foreignUlid('weekly_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
                    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                    $table->timestamps();

                    $table->unique(['weekly_plan_id', 'user_id'], 'document_user_shares_weekly_plan_user_unique');
                    $table->unique(['weekly_report_id', 'user_id'], 'document_user_shares_weekly_report_user_unique');
                    $table->index('weekly_plan_id', 'document_user_shares_weekly_plan_id_index');
                    $table->index('weekly_report_id', 'document_user_shares_weekly_report_id_index');
                    $table->index('user_id');
                });
            }

            if (Schema::hasTable('document_share_events') && Schema::hasColumn('document_share_events', 'document_id')) {
                Schema::drop('document_share_events');
                Schema::create('document_share_events', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignUlid('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
                    $table->foreignUlid('weekly_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
                    $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                    $table->foreignUlid('target_user_id')->constrained('users')->cascadeOnDelete();
                    $table->enum('event_type', ['added', 'removed']);
                    $table->timestamps();

                    $table->index(['target_user_id', 'created_at']);
                    $table->index(['weekly_plan_id', 'event_type'], 'document_share_events_weekly_plan_event_type_index');
                    $table->index(['weekly_report_id', 'event_type'], 'document_share_events_weekly_report_event_type_index');
                });
            }

            if (Schema::hasTable('monthly_reports') && Schema::hasColumn('monthly_reports', 'legacy_document_id')) {
                Schema::drop('monthly_report_off_plan_items');
                Schema::drop('monthly_report_planned_items');
                Schema::drop('monthly_reports');
                Schema::create('monthly_reports', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                    $table->unsignedTinyInteger('month');
                    $table->unsignedSmallInteger('year');
                    $table->string('title')->nullable();
                    $table->enum('state', ['draft', 'submitted'])->default('draft');
                    $table->string('file_path')->nullable();
                    $table->string('attachment')->nullable();
                    $table->unsignedBigInteger('file_size')->nullable();
                    $table->string('original_filename')->nullable();
                    $table->text('month_summary')->nullable();
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index(['user_id', 'year', 'month']);
                    $table->unique(['user_id', 'year', 'month'], 'monthly_reports_user_year_month_unique');
                });
                Schema::create('monthly_report_planned_items', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->foreignUlid('monthly_report_id')->constrained('monthly_reports')->cascadeOnDelete();
                    $table->foreignUlid('year_plan_activity_id')->nullable()->constrained('year_plan_activities')->nullOnDelete();
                    $table->string('implementation_status')->nullable();
                    $table->text('achievements')->nullable();
                    $table->text('challenges')->nullable();
                    $table->text('next_steps')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['monthly_report_id', 'sort_order'], 'monthly_report_planned_items_order_idx');
                });
                Schema::create('monthly_report_off_plan_items', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->foreignUlid('monthly_report_id')->constrained('monthly_reports')->cascadeOnDelete();
                    $table->string('title')->nullable();
                    $table->text('description')->nullable();
                    $table->string('status')->nullable();
                    $table->text('notes')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['monthly_report_id', 'sort_order'], 'monthly_report_off_plan_items_order_idx');
                });
            }

            if (Schema::hasTable('action_logs') && Schema::hasColumn('action_logs', 'legacy_document_id')) {
                Schema::drop('action_log_task_history');
                Schema::drop('action_log_task_assignees');
                Schema::drop('action_log_tasks');
                Schema::drop('action_logs');
                Schema::create('action_logs', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                    $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
                    $table->string('title')->nullable();
                    $table->enum('state', ['draft', 'submitted'])->default('draft');
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index(['period_id', 'state']);
                    $table->index('user_id');
                });
                Schema::create('action_log_tasks', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->foreignUlid('action_log_id')->constrained('action_logs')->cascadeOnDelete();
                    $table->string('task_uid', 80);
                    $table->unsignedInteger('task_no')->default(0);
                    $table->text('task')->nullable();
                    $table->enum('progress', ['Not Started', 'In Progress', 'Done', 'Cancelled'])->default('Not Started');
                    $table->date('due_date')->nullable();
                    $table->date('compilation_date')->nullable();
                    $table->text('notes_progress')->nullable();
                    $table->text('status_reason')->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->unique(['action_log_id', 'task_uid'], 'action_log_tasks_uid_unique');
                    $table->index(['action_log_id', 'sort_order']);
                });
                Schema::create('action_log_task_assignees', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignUlid('action_log_task_id')->constrained('action_log_tasks')->cascadeOnDelete();
                    $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                    $table->timestamps();

                    $table->unique(['action_log_task_id', 'user_id'], 'action_log_task_assignees_unique');
                    $table->index('user_id');
                });
                Schema::create('action_log_task_history', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignUlid('action_log_task_id')->constrained('action_log_tasks')->cascadeOnDelete();
                    $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
                    $table->string('period_label')->nullable();
                    $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
                    $table->string('from_progress')->nullable();
                    $table->string('to_progress')->nullable();
                    $table->text('notes_progress')->nullable();
                    $table->text('status_reason')->nullable();
                    $table->timestamp('changed_at')->nullable();
                    $table->string('source')->nullable();
                    $table->timestamps();

                    $table->index(['action_log_task_id', 'changed_at']);
                });
            }

            if (Schema::hasTable('weekly_activity_assignments') && Schema::hasColumn('weekly_activity_assignments', 'source_document_id')) {
                Schema::drop('weekly_activity_assignments');
                Schema::create('weekly_activity_assignments', function (Blueprint $table): void {
                    $table->ulid('id')->primary();
                    $table->ulid('source_weekly_plan_id')->nullable();
                    $table->ulid('source_user_id');
                    $table->ulid('assignee_user_id');
                    $table->ulid('period_id')->nullable();
                    $table->string('activity_uid', 64);
                    $table->string('status', 20)->default('preview');
                    $table->json('activity_payload');
                    $table->timestamps();

                    $table->unique(
                        ['source_weekly_plan_id', 'assignee_user_id', 'activity_uid'],
                        'weekly_activity_assignments_plan_assignee_uid_unique'
                    );
                    $table->index(['assignee_user_id', 'status'], 'weekly_activity_assignments_assignee_status_index');
                    $table->index(['period_id', 'assignee_user_id'], 'weekly_activity_assignments_period_assignee_index');
                    $table->index(
                        ['source_weekly_plan_id', 'period_id'],
                        'weekly_activity_assignments_plan_period_index'
                    );
                });
            }

            if (Schema::hasTable('weekly_plan_activities') && Schema::hasColumn('weekly_plan_activities', 'carry_over_source_report_id')) {
                Schema::table('weekly_plan_activities', function (Blueprint $table): void {
                    $table->dropColumn('carry_over_source_report_id');
                });
            }
        } finally {
            $this->enableForeignKeys();
        }
    }

    private function disableForeignKeys(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF');
        }
    }

    private function enableForeignKeys(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}
