<?php

namespace App\Console\Commands;

use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use Illuminate\Console\Command;

class FillPettyPaidDate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'petty:fill-paid-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
      public function handle()
    {
        $updatedCount = 0;

        $requests = FinanceRequest::where('status', 'paid')->get();

        foreach ($requests as $request) {
            $approval = RequestApproval::where('request_id', $request->id)
                ->where('action', 'paid')
                ->orderBy('acted_at', 'desc')
                ->first();

            if ($approval) {
                $request->paid_at = $approval->acted_at ?? $approval->created_at;
                $request->save();
                $updatedCount++;
            }
        }

        $this->info("Updated {$updatedCount} petty records with paid_date.");
    }
}
