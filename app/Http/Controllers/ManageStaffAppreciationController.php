<?php

namespace App\Http\Controllers;

use App\Models\NominationPeriod;
use App\Services\StaffAppreciationService;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ManageStaffAppreciationController extends Controller
{
    public function __construct(private readonly StaffAppreciationService $service) {}

    public function index(): Response
    {
        $this->authorize('viewAny', NominationPeriod::class);

        $periods = $this->service->periodsForHistory();

        // Prefer the currently open window; otherwise the most recent period.
        $currentPeriod = $this->service->currentOpenPeriod() ?? $periods->first();
        $previousPeriod = $currentPeriod ? $this->service->previousPeriodOf($currentPeriod) : null;

        return Inertia::render('Management/Appreciation/Index', [
            'periods' => $periods->map(fn (NominationPeriod $period) => [
                ...$this->periodData($period),
                'stats' => $this->service->participationStats($period),
            ])->values(),
            'currentPeriod' => $currentPeriod ? $this->periodData($currentPeriod) : null,
            'previousPeriod' => $previousPeriod ? $this->periodData($previousPeriod) : null,
            'currentDetail' => $currentPeriod ? $this->detailData($currentPeriod) : null,
            'previousStats' => $previousPeriod ? $this->service->participationStats($previousPeriod) : null,
        ]);
    }

    public function show(NominationPeriod $nomination_period): Response
    {
        $this->authorize('view', $nomination_period);

        return Inertia::render('Management/Appreciation/Show', [
            'period' => $this->periodData($nomination_period),
            'detail' => $this->detailData($nomination_period),
            'indexUrl' => route('management.appreciation.index'),
        ]);
    }

    private function periodData(NominationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'label' => $period->label(),
            'opensAt' => $period->opens_at->format('M d, Y H:i'),
            'closesAt' => $period->closes_at->format('M d, Y H:i'),
            'isWindowOpen' => $period->isOpen() && $period->isWindowOpenNow(),
            'showUrl' => route('management.appreciation.show', $period),
        ];
    }

    private function detailData(NominationPeriod $period): array
    {
        return [
            'stats' => $this->service->participationStats($period),
            'tallies' => $this->talliesData($this->service->nomineeTallies($period)),
            'reasons' => $this->service->reasons($period)->map(fn ($reason) => [
                'id' => $reason->id,
                'nomineeName' => $reason->nominee?->name ?? 'Unknown user',
                'reason' => $reason->reason,
                'createdAt' => $reason->created_at?->format('M d'),
            ])->values(),
        ];
    }

    private function talliesData(Collection $tallies): Collection
    {
        return $tallies->map(fn (array $row) => [
            'user' => [
                'name' => $row['user']?->name ?? 'Unknown user',
                'title' => $row['user']?->title,
                'department' => $row['user']?->department?->name,
                'avatar' => $row['user']?->file ? asset($row['user']->file) : asset('assets/images/profile.png'),
            ],
            'votes' => $row['votes'],
        ])->values();
    }
}
