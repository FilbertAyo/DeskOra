<?php

namespace App\Http\Controllers;

use App\Models\NominationPeriod;
use App\Services\StaffAppreciationService;
use Illuminate\View\View;

class ManageStaffAppreciationController extends Controller
{
    public function __construct(private readonly StaffAppreciationService $service) {}

    public function index(): View
    {
        $this->authorize('viewAny', NominationPeriod::class);

        $periods = $this->service->periodsForHistory();

        // Prefer the currently open window; otherwise the most recent period.
        $currentPeriod = $this->service->currentOpenPeriod() ?? $periods->first();
        $previousPeriod = $currentPeriod ? $this->service->previousPeriodOf($currentPeriod) : null;

        return view('management.staff-appreciation.index', [
            'periods' => $periods,
            'currentPeriod' => $currentPeriod,
            'previousPeriod' => $previousPeriod,
            'currentStats' => $currentPeriod ? $this->service->participationStats($currentPeriod) : null,
            'previousStats' => $previousPeriod ? $this->service->participationStats($previousPeriod) : null,
            'currentTallies' => $currentPeriod ? $this->service->nomineeTallies($currentPeriod) : collect(),
            'currentReasons' => $currentPeriod ? $this->service->reasons($currentPeriod) : collect(),
        ]);
    }

    public function show(NominationPeriod $nomination_period): View
    {
        $this->authorize('view', $nomination_period);

        return view('management.staff-appreciation.show', [
            'period' => $nomination_period,
            'stats' => $this->service->participationStats($nomination_period),
            'tallies' => $this->service->nomineeTallies($nomination_period),
            'reasons' => $this->service->reasons($nomination_period),
            'isWindowOpen' => $nomination_period->isOpen() && $nomination_period->isWindowOpenNow(),
        ]);
    }
}
