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
    protected $signature = 'work-periods:auto-create
                            {--ensure-next : Ensure immediate next week period exists}
                            {--close-ended : Close open periods already ended}';

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
        $this->info('Date: '.now()->format('Y-m-d H:i:s'));

        try {
            $ensureNext = (bool) $this->option('ensure-next');
            $closeEnded = (bool) $this->option('close-ended');
            if (! $ensureNext && ! $closeEnded) {
                $ensureNext = true;
                $closeEnded = true;
            }

            $results = [
                'closed' => false,
                'created' => false,
                'closed_period' => null,
                'created_period' => null,
                'messages' => [],
            ];

            if ($closeEnded) {
                $closeResult = $this->workPeriodService->closeEndedWeeks();
                if ($closeResult['count'] > 0) {
                    $results['closed'] = true;
                    $results['closed_period'] = $closeResult['periods']->last();
                    $results['messages'][] = "Closed {$closeResult['count']} ended week(s).";
                } else {
                    $results['messages'][] = 'No ended weeks to close.';
                }
            }

            if ($ensureNext) {
                $newPeriod = $this->workPeriodService->createNextWeek();
                if ($newPeriod) {
                    $results['created'] = true;
                    $results['created_period'] = $newPeriod;
                    $results['messages'][] = 'Ensured next week exists: '.$newPeriod->display_name;
                }
            }

            // Display results
            if ($closeEnded && $results['closed']) {
                $this->info('✓ Closed previous week: '.($results['closed_period'] ? $results['closed_period']->display_name : 'Unknown'));
            } elseif ($closeEnded) {
                $this->warn('⚠ No week to close (or already closed)');
            }

            if ($ensureNext && $results['created']) {
                $period = $results['created_period'];
                $this->info('✓ Created new week: '.$period->display_name);
                $this->info('  - Date Range: '.$period->date_range);
                $this->info('  - Plan Deadline: '.$period->plan_deadline->format('M d, Y'));
                $this->info('  - Report Deadline: '.$period->report_deadline->format('M d, Y'));

                // Check if it's a new year
                if ($period->week_number === 1) {
                    $this->info('  🎉 New Year! Week 1 of '.$period->year);
                }
            } elseif ($ensureNext) {
                $this->error('✗ Failed to create next week');
                foreach ($results['messages'] as $message) {
                    $this->error('  - '.$message);
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
            $this->error('Error during work period automation: '.$e->getMessage());
            Log::error('Work Period Automation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
