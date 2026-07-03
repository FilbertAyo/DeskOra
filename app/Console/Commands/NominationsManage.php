<?php

namespace App\Console\Commands;

use App\Services\StaffAppreciationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NominationsManage extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nominations:manage
                            {--open-now : Immediately open a nomination window (default: current month, until month end)}
                            {--closes= : Close date/time for --open-now (e.g. "2026-07-05"); defaults to end of the current month}
                            {--year= : Period year key for --open-now (default: current year)}
                            {--month= : Period month key for --open-now (default: current month)}';

    /**
     * @var string
     */
    protected $description = 'Open the monthly staff appreciation nomination window when due and close ended windows';

    public function handle(StaffAppreciationService $service): int
    {
        $this->info('Managing staff appreciation nomination periods...');

        try {
            if ($this->option('open-now')) {
                $closesAt = null;
                if ($this->option('closes')) {
                    $closesAt = \Carbon\Carbon::parse($this->option('closes'), config('app.timezone'));
                    // A bare date (no time) should cover the whole day.
                    if ($closesAt->format('H:i:s') === '00:00:00') {
                        $closesAt->endOfDay();
                    }
                }

                $year = $this->option('year') !== null ? (int) $this->option('year') : null;
                $month = $this->option('month') !== null ? (int) $this->option('month') : null;

                $period = $service->openNow(null, $closesAt, $year, $month);
                $this->info('Opened nomination window now: '.$period->label()
                    .' (opens '.$period->opens_at->format('M d, Y H:i')
                    .', closes '.$period->closes_at->format('M d, Y H:i').')');

                return self::SUCCESS;
            }

            $result = $service->ensureAndCloseFor();

            if ($result['closed'] > 0) {
                $this->info("Closed {$result['closed']} ended nomination period(s).");
            }

            if ($result['ensured']) {
                $this->info('Active nomination period: '.$result['ensured']->label());
            } else {
                $this->line('No nomination window is currently open.');
            }

            Log::info('Nomination period automation completed', [
                'closed' => $result['closed'],
                'ensured' => $result['ensured']?->id,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Nomination period automation failed: '.$e->getMessage());
            Log::error('Nomination period automation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
