<?php

namespace App\Services\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryVerificationResult;
use App\Models\InventoryVerificationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryVerificationProcessor
{
    public function complete(InventoryVerificationSession $session, User $user): void
    {
        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'status' => 'This verification session is already completed.',
            ]);
        }

        DB::transaction(function () use ($session, $user) {
            $session->load(['results.asset']);

            foreach ($session->results as $result) {
                $this->applyResult($result);
            }

            $session->update([
                'status' => InventoryVerificationSession::STATUS_COMPLETED,
                'completed_by_user_id' => $user->id,
                'completed_at' => now(),
            ]);
        });
    }

    private function applyResult(InventoryVerificationResult $result): void
    {
        $asset = $result->asset;
        if (! $asset || $asset->isDisposed() || $result->result === InventoryVerificationResult::RESULT_PENDING) {
            return;
        }

        $updates = [];

        if ($result->result === InventoryVerificationResult::RESULT_MISSING) {
            $updates['status'] = InventoryAsset::STATUS_MISSING;
        }

        if ($result->result === InventoryVerificationResult::RESULT_DAMAGED) {
            $updates['condition'] = InventoryAsset::CONDITION_DAMAGED;
        }

        if ($result->result === InventoryVerificationResult::RESULT_LOCATION_CHANGED && $result->recorded_location_id) {
            $updates['location_id'] = $result->recorded_location_id;
        }

        if ($result->result === InventoryVerificationResult::RESULT_PERSON_CHANGED && $result->recorded_responsible_user_id) {
            $updates['responsible_user_id'] = $result->recorded_responsible_user_id;
        }

        if ($updates !== []) {
            $asset->update($updates);
        }
    }
}
