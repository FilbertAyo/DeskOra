<?php

namespace App\Console\Commands;

use App\Services\DailyDigestService;
use Illuminate\Console\Command;

class SendDailyDigests extends Command
{
    protected $signature = 'workhub:send-daily-digests';

    protected $description = 'Send rule-based daily digests to active users';

    public function handle(DailyDigestService $dailyDigestService): int
    {
        $sent = $dailyDigestService->sendToAllActiveUsers();
        $this->info("Daily digests processed for {$sent} active users.");

        return self::SUCCESS;
    }
}
