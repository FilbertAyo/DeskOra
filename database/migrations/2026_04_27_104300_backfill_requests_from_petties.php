<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('requests') || ! DB::getSchemaBuilder()->hasTable('petties')) {
            return;
        }

        $now = now();
        $pettyRows = DB::table('petties')->orderBy('created_at')->get();

        foreach ($pettyRows as $petty) {
            $requestType = (string) ($petty->request_type ?? 'Petty Cash');
            $requestId = (string) Str::ulid();
            $requestNo = ! empty($petty->code) ? (string) $petty->code : ('REQ-'.strtoupper(Str::random(8)));
            $submittedAt = $petty->created_at ?? $now;

            // Ensure unique request number during reruns and conflicting legacy codes.
            while (DB::table('requests')->where('request_no', $requestNo)->exists()) {
                $requestNo = 'REQ-'.strtoupper(Str::random(10));
            }

            $requestPayload = [
                'id' => $requestId,
                'request_no' => $requestNo,
                'request_type' => in_array($requestType, ['Petty Cash', 'Reimbursement', 'Staff Requisition', 'Letter'], true) ? $requestType : 'Petty Cash',
                'user_id' => $petty->user_id,
                'department_id' => $petty->department_id,
                'status' => $petty->status ?? 'pending',
                'submitted_at' => $submittedAt,
                'approved_at' => null,
                'paid_at' => $petty->paid_date,
                'created_at' => $petty->created_at ?? $now,
                'updated_at' => $petty->updated_at ?? $now,
            ];

            DB::table('requests')->insert($requestPayload);

            if ($requestType === 'Staff Requisition') {
                DB::table('requisition_requests')->insert([
                    'id' => (string) Str::ulid(),
                    'request_id' => $requestId,
                    'project_program' => $petty->project_program ?? null,
                    'activity_code' => $petty->activity_code ?? null,
                    'activity_title' => $petty->activity_title ?? ($petty->request_for ?? null),
                    'budget_code' => $petty->budget_code ?? null,
                    'date_of_request' => $petty->date_of_request ?? null,
                    'date_of_requirement' => $petty->date_of_requirement ?? null,
                    'reason' => $petty->reason ?? null,
                    'amount' => $petty->amount ?? 0,
                    'created_at' => $petty->created_at ?? $now,
                    'updated_at' => $petty->updated_at ?? $now,
                ]);
            } else {
                DB::table('petty_requests')->insert([
                    'id' => (string) Str::ulid(),
                    'request_id' => $requestId,
                    'replenishment_id' => $petty->replenishment_id ?? null,
                    'request_for' => $petty->request_for ?? null,
                    'amount' => $petty->amount ?? 0,
                    'reason' => $petty->reason ?? null,
                    'is_transporter' => (bool) ($petty->is_transporter ?? false),
                    'attachment' => $petty->attachment ?? null,
                    'paid_date' => $petty->paid_date ?? null,
                    'created_at' => $petty->created_at ?? $now,
                    'updated_at' => $petty->updated_at ?? $now,
                ]);
            }

            DB::table('request_approvals')->insert([
                'id' => (string) Str::ulid(),
                'request_id' => $requestId,
                'step' => 'system',
                'action' => 'created',
                'actor_id' => $petty->user_id,
                'comment' => null,
                'acted_at' => $submittedAt,
                'meta' => json_encode(['source' => 'legacy-petty', 'legacy_petty_id' => $petty->id]),
                'created_at' => $petty->created_at ?? $now,
                'updated_at' => $petty->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('request_approvals') || ! DB::getSchemaBuilder()->hasTable('requests')) {
            return;
        }

        $legacyRequestIds = DB::table('request_approvals')
            ->where('step', 'system')
            ->where('action', 'created')
            ->whereRaw("JSON_EXTRACT(meta, '$.source') = 'legacy-petty'")
            ->pluck('request_id')
            ->all();

        if (empty($legacyRequestIds)) {
            return;
        }

        DB::table('requisition_items')->whereIn('request_id', $legacyRequestIds)->update(['request_id' => null]);
        DB::table('request_approvals')->whereIn('request_id', $legacyRequestIds)->delete();
        DB::table('petty_requests')->whereIn('request_id', $legacyRequestIds)->delete();
        DB::table('requisition_requests')->whereIn('request_id', $legacyRequestIds)->delete();
        DB::table('requests')->whereIn('id', $legacyRequestIds)->delete();
    }
};
