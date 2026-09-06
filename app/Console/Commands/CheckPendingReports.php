<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckPendingReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:check-pending 
                            {--week= : Check specific week (format: YYYY-WW, e.g., 2024-45)}
                            {--month= : Check specific month (format: YYYY-MM, e.g., 2024-11)}
                            {--type= : Check specific type (weekly_plan, weekly_report, monthly_report)}
                            {--verbose : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for employees who haven\'t submitted weekly plans/reports or monthly reports';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Checking for Pending Reports...');
        $this->newLine();

        // Get options
        $week = $this->option('week');
        $month = $this->option('month');
        $type = $this->option('type');
        $verbose = $this->option('verbose');

        // Determine date range
        if ($week) {
            [$year, $weekNum] = explode('-', $week);
            $startDate = Carbon::now()->setISODate($year, $weekNum)->startOfWeek();
            $endDate = $startDate->copy()->endOfWeek();
            $periodLabel = "Week {$weekNum} of {$year}";
        } elseif ($month) {
            [$year, $monthNum] = explode('-', $month);
            $startDate = Carbon::create($year, $monthNum, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $periodLabel = Carbon::create($year, $monthNum, 1)->format('F Y');
        } else {
            // Default: Current week for weekly, current month for monthly
            $startDate = Carbon::now()->startOfWeek();
            $endDate = Carbon::now()->endOfWeek();
            $periodLabel = 'Current Week ('.$startDate->format('M d').' - '.$endDate->format('M d, Y').')';
        }

        // For monthly reports, use month range
        $monthStartDate = Carbon::now()->startOfMonth();
        $monthEndDate = Carbon::now()->endOfMonth();

        $this->info("📅 Period: {$periodLabel}");
        $this->info("📆 Weekly Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        if (! $week) {
            $this->info("📆 Monthly Date Range: {$monthStartDate->format('Y-m-d')} to {$monthEndDate->format('Y-m-d')}");
        }
        $this->newLine();

        // Get all employees
        $employees = User::role('employee')->where('status', 'active')->get();

        if ($employees->isEmpty()) {
            $this->warn('⚠️  No active employees found.');

            return Command::SUCCESS;
        }

        $this->info("👥 Found {$employees->count()} active employee(s)");
        $this->newLine();

        // Check pending documents
        $pendingWeeklyPlans = [];
        $pendingWeeklyReports = [];
        $pendingMonthlyReports = [];

        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $employee) {
            // Check Weekly Plan
            if (! $type || $type === 'weekly_plan') {
                $hasWeeklyPlan = $this->hasSubmittedDocument(
                    $employee,
                    Document::TYPE_WEEKLY_PLAN,
                    $startDate,
                    $endDate
                );

                if (! $hasWeeklyPlan) {
                    $pendingWeeklyPlans[] = $employee;
                }
            }

            // Check Weekly Report
            if (! $type || $type === 'weekly_report') {
                $hasWeeklyReport = $this->hasSubmittedDocument(
                    $employee,
                    Document::TYPE_WEEKLY_REPORT,
                    $startDate,
                    $endDate
                );

                if (! $hasWeeklyReport) {
                    $pendingWeeklyReports[] = $employee;
                }
            }

            // Check Monthly Report (always check current month for monthly reports)
            if (! $type || $type === 'monthly_report') {
                $hasMonthlyReport = $this->hasSubmittedDocument(
                    $employee,
                    Document::TYPE_MONTHLY_REPORT,
                    $monthStartDate,
                    $monthEndDate
                );

                if (! $hasMonthlyReport) {
                    $pendingMonthlyReports[] = $employee;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        // Only show monthly reports if not filtering by specific week
        if (! $week) {
            $this->displayResults('Monthly Reports', $pendingMonthlyReports, $verbose);
        }

        $this->displayResults('Weekly Plans', $pendingWeeklyPlans, $verbose);
        $this->displayResults('Weekly Reports', $pendingWeeklyReports, $verbose);

        // Summary
        $this->newLine();
        $totalPending = count($pendingWeeklyPlans) + count($pendingWeeklyReports) + count($pendingMonthlyReports);

        if ($totalPending > 0) {
            $this->warn("⚠️  Total: {$totalPending} pending document(s)");

            return Command::FAILURE;
        } else {
            $this->info('✅ All employees have submitted their required documents!');

            return Command::SUCCESS;
        }
    }

    /**
     * Check if employee has submitted a document of specific type in the date range
     */
    private function hasSubmittedDocument(User $employee, string $type, Carbon $startDate, Carbon $endDate): bool
    {
        return Document::where('user_id', $employee->id)
            ->where('type', $type)
            ->where('state', Document::STATE_SUBMITTED)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->exists();
    }

    /**
     * Display results for a document type
     */
    private function displayResults(string $type, array $employees, bool $verbose): void
    {
        if (empty($employees)) {
            $this->info("✅ {$type}: All employees submitted");

            return;
        }

        $this->warn("⚠️  {$type}: {$this->getCountText(count($employees))} pending");

        if ($verbose || count($employees) <= 10) {
            $this->table(
                ['#', 'Name', 'Email', 'Department'],
                collect($employees)->map(function ($employee, $index) {
                    return [
                        $index + 1,
                        $employee->name,
                        $employee->email,
                        $employee->department?->name ?? 'N/A',
                    ];
                })->toArray()
            );
        } else {
            $this->line('   '.collect($employees)->pluck('name')->implode(', '));
        }

        $this->newLine();
    }

    /**
     * Get count text
     */
    private function getCountText(int $count): string
    {
        return $count === 1 ? '1 employee' : "{$count} employees";
    }
}
