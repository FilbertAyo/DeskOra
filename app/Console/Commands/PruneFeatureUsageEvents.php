<?php

namespace App\Console\Commands;

use App\Models\FeatureUsageEvent;
use Illuminate\Console\Command;

class PruneFeatureUsageEvents extends Command
{
    protected $signature = 'feature-usage:prune
                            {--days= : Override retention days from config}';

    protected $description = 'Delete feature usage events older than the configured retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('feature-usage.retention_days', 365));
        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = FeatureUsageEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} feature usage event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
