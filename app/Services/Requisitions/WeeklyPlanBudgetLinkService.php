<?php

namespace App\Services\Requisitions;

use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use Illuminate\Validation\ValidationException;

class WeeklyPlanBudgetLinkService
{
    /**
     * Weekly plans the staff member may link, including plans management has not provided yet.
     *
     * @return list<array{id: string, label: string, isCurrentWeek: bool, items: list<array{description: string, unit: string, cost: float}>}>
     */
    public function optionsForUser(User $user): array
    {
        $currentPeriodId = WorkPeriod::getCurrent()?->id;

        return WeeklyPlan::query()
            ->with(['budgetItems', 'period'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (WeeklyPlan $plan) use ($currentPeriodId): array {
                $period = $plan->period;
                $label = trim((string) ($plan->title ?? ''));
                if ($period) {
                    $label = sprintf('Week %s · %s', $period->week_number, $period->year);
                    $range = trim((string) ($period->date_range ?? ''));
                    if ($range !== '') {
                        $label .= ' · '.$range;
                    }
                }
                if ($label === '') {
                    $label = 'Weekly plan';
                }

                return [
                    'id' => (string) $plan->id,
                    'label' => $label,
                    'isCurrentWeek' => $currentPeriodId !== null && (string) $plan->period_id === (string) $currentPeriodId,
                    'items' => $plan->budgetItems
                        ->map(fn (WeeklyPlanBudgetItem $item): array => [
                            'description' => self::lineDescription(
                                (string) ($item->item ?? ''),
                                (string) ($item->description_purpose ?? ''),
                            ),
                            'unit' => (string) ($item->unit ?? ''),
                            'cost' => (float) ($item->amount_numeric ?? 0),
                        ])
                        ->filter(fn (array $row): bool => $row['description'] !== '' || $row['unit'] !== '' || $row['cost'] > 0)
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    public function ownedPlanId(?string $planId, User $user): ?string
    {
        $planId = trim((string) $planId);
        if ($planId === '') {
            return null;
        }

        $ownsPlan = WeeklyPlan::query()
            ->where('id', $planId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $ownsPlan) {
            throw ValidationException::withMessages([
                'weekly_plan_id' => 'Select one of your own weekly plans.',
            ]);
        }

        return $planId;
    }

    public function markCompleted(?string $weeklyPlanId): void
    {
        $weeklyPlanId = trim((string) $weeklyPlanId);
        if ($weeklyPlanId === '') {
            return;
        }

        $plan = WeeklyPlan::query()
            ->with('budgetItems.review')
            ->find($weeklyPlanId);
        if (! $plan) {
            return;
        }

        if ($plan->budget_review_status === Document::BUDGET_REVIEW_COMPLETED) {
            return;
        }

        foreach ($plan->budgetItems as $item) {
            WeeklyPlanBudgetReview::query()->updateOrCreate(
                ['weekly_plan_budget_item_id' => $item->id],
                ['approval_stage' => Document::BUDGET_REVIEW_STAGE_COMPLETED]
            );
        }

        $plan->update([
            'budget_review_status' => Document::BUDGET_REVIEW_COMPLETED,
        ]);

        $document = Document::query()->find($plan->id);
        if ($document) {
            $document->budget_review_status = Document::BUDGET_REVIEW_COMPLETED;
            $document->save();
        }
    }

    public static function lineDescription(string $item, string $purpose): string
    {
        $item = trim($item);
        $purpose = trim($purpose);
        if ($item !== '' && $purpose !== '') {
            $description = $item.' — '.$purpose;
        } else {
            $description = $item !== '' ? $item : $purpose;
        }

        if (mb_strlen($description) <= 255) {
            return $description;
        }

        return mb_substr($description, 0, 255);
    }
}
