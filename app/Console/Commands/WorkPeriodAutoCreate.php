<?php

namespace App\Console\Commands;

use App\Services\WorkPeriodService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WorkPeriodAutoCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'work-periods:auto-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically create next work period (next week only) and close ended week';

    /**
     * WorkPeriodService instance
     *
     * @var WorkPeriodService
     */
    protected $workPeriodService;

    /**
     * Create a new command instance.
     */
    public function __construct(WorkPeriodService $workPeriodService)
    {
        parent::__construct();
        $this->workPeriodService = $workPeriodService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting work period automation...');
        $this->info('Date: ' . now()->format('Y-m-d H:i:s'));

        try {
            // Process weekly automation (close current week and create next week)
            $results = $this->workPeriodService->processWeeklyAutomation();

            // Display results
            if ($results['closed']) {
                $this->info('✓ Closed previous week: ' . ($results['closed_period'] ? $results['closed_period']->display_name : 'Unknown'));
            } else {
                $this->warn('⚠ No week to close (or already closed)');
            }

            if ($results['created']) {
                $period = $results['created_period'];
                $this->info('✓ Created new week: ' . $period->display_name);
                $this->info('  - Date Range: ' . $period->date_range);
                $this->info('  - Plan Deadline: ' . $period->plan_deadline->format('M d, Y'));
                $this->info('  - Report Deadline: ' . $period->report_deadline->format('M d, Y'));
                
                // Check if it's a new year
                if ($period->week_number === 1) {
                    $this->info('  🎉 New Year! Week 1 of ' . $period->year);
                }
            } else {
                $this->error('✗ Failed to create next week');
                foreach ($results['messages'] as $message) {
                    $this->error('  - ' . $message);
                }
            }

            // Log the results
            Log::info('Work Period Automation Completed', [
                'closed' => $results['closed'],
                'created' => $results['created'],
                'messages' => $results['messages'],
            ]);

            $this->info('Work period automation completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error during work period automation: ' . $e->getMessage());
            Log::error('Work Period Automation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }
    }
}
