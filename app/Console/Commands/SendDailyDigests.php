<?php

namespace App\Console\Commands;

use App\Services\DailyDigestService;
use Illuminate\Console\Command;

class SendDailyDigests extends Command
{
    protected $signature = 'deskora:send-digests';

    protected $description = 'Send rule-based digests to active users (scheduled Mon & Sat)';

    public function handle(DailyDigestService $dailyDigestService): int
    {
        $sent = $dailyDigestService->sendToAllActiveUsers();
        $this->info("Digests processed for {$sent} active users.");

        return self::SUCCESS;
    }
}
