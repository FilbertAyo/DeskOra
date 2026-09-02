<?php

namespace App\Services\Inventory;

use App\Mail\InventoryAssignmentReportMail;
use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class InventoryAssignmentReportService
{
    public function assetsForUser(User $user): Collection
    {
        return InventoryAsset::query()
            ->with(['category', 'location.parent', 'department.parent', 'responsibleUser'])
            ->inUserCustody($user)
            ->orderBy('asset_code')
            ->get();
    }

    public function sendToUser(User $user): bool
    {
        if (! filled($user->email)) {
            return false;
        }

        $assets = $this->assetsForUser($user);

        if ($assets->isEmpty()) {
            return false;
        }

        Mail::to($user->email)->send(new InventoryAssignmentReportMail($user, $assets));

        return true;
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    public function sendToAllStaff(): array
    {
        $sent = 0;
        $skipped = 0;

        User::query()
            ->active()
            ->whereNotNull('email')
            ->orderBy('name')
            ->each(function (User $user) use (&$sent, &$skipped) {
                if ($this->sendToUser($user)) {
                    $sent++;
                } else {
                    $skipped++;
                }
            });

        return compact('sent', 'skipped');
    }
}
