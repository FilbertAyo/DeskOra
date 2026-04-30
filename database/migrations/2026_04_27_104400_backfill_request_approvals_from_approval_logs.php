<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function resolveStep(string $requestType, string $action, int $approvedCount): string
    {
        if ($action === 'paid') {
            return 'payment';
        }

        if (in_array($action, ['rejected', 'resubmission', 'resubmitted', 'created'], true)) {
            return 'system';
        }

        if ($requestType === 'Staff Requisition') {
            return match ($approvedCount) {
                0 => 'review',
                1 => 'approve',
                default => 'authorize',
            };
        }

        return match ($approvedCount) {
            0 => 'review',
            1 => 'approve',
            default => 'authorize',
        };
    }

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('approval_logs') || ! DB::getSchemaBuilder()->hasTable('requests')) {
            return;
        }

        $now = now();

        // Build mapping from legacy petty id to new request id.
        $pettyToRequest = DB::table('request_approvals')
            ->where('step', 'system')
            ->where('action', 'created')
            ->whereRaw("JSON_EXTRACT(meta, '$.source') = 'legacy-petty'")
            ->select('request_id', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.legacy_petty_id')) as legacy_petty_id"))
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->legacy_petty_id => (string) $row->request_id])
            ->all();

        if (empty($pettyToRequest)) {
            return;
        }

        $logsByPetty = DB::table('approval_logs')
            ->whereNotNull('petty_id')
            ->orderBy('created_at')
            ->get()
            ->groupBy('petty_id');

        foreach ($logsByPetty as $pettyId => $logs) {
            $requestId = $pettyToRequest[(string) $pettyId] ?? null;
            if (! $requestId) {
                continue;
            }

            $requestType = (string) (DB::table('requests')->where('id', $requestId)->value('request_type') ?? 'Petty Cash');
            $approvedCount = 0;

            foreach ($logs as $log) {
                $action = strtolower((string) $log->action);
                if (! in_array($action, ['approved', 'rejected', 'resubmission', 'resubmitted', 'paid', 'created'], true)) {
                    $action = 'created';
                }

                $step = $this->resolveStep($requestType, $action, $approvedCount);
                if ($action === 'approved') {
                    $approvedCount++;
                }

                DB::table('request_approvals')->insert([
                    'id' => (string) Str::ulid(),
                    'request_id' => $requestId,
                    'step' => $step,
                    'action' => $action,
                    'actor_id' => $log->user_id,
                    'comment' => $log->comment,
                    'acted_at' => $log->created_at ?? $now,
                    'meta' => json_encode(['source' => 'legacy-approval-log', 'legacy_approval_log_id' => $log->id]),
                    'created_at' => $log->created_at ?? $now,
                    'updated_at' => $log->updated_at ?? $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('request_approvals')) {
            return;
        }

        DB::table('request_approvals')
            ->whereRaw("JSON_EXTRACT(meta, '$.source') = 'legacy-approval-log'")
            ->delete();
    }
};
