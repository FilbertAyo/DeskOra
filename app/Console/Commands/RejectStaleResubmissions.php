<?php

namespace App\Console\Commands;

use App\Models\Request as FinanceRequest;
use Illuminate\Console\Command;

class RejectStaleResubmissions extends Command
{
    protected $signature = 'petty:reject-stale';

    protected $description = 'Reject petty cash requests with resubmission status older than 24 hours';

    public function handle()
    {
        $cutoff = now()->subHours(24);

        $updated = FinanceRequest::where('status', 'resubmission')
            ->where('updated_at', '<=', $cutoff)
            ->update(['status' => 'rejected']);

        $this->info("Updated $updated petty cash requests to 'rejected'.");
    }

    public function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command(static::class)->hourly(); // or daily()
    }
}
