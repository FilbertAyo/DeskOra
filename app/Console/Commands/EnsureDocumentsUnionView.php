<?php

namespace App\Console\Commands;

use App\Database\DocumentsUnionView;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureDocumentsUnionView extends Command
{
    protected $signature = 'documents:ensure-union-view
                            {--force : Recreate the view even if it already exists}';

    protected $description = 'Create or recreate the documents SQL view over weekly_plans, weekly_reports, monthly_reports, and action_logs';

    public function handle(): int
    {
        $requiredTables = ['weekly_plans', 'weekly_reports', 'monthly_reports', 'action_logs'];
        foreach ($requiredTables as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                $this->error("Missing required table: {$table}. Run domain migrations first.");

                return self::FAILURE;
            }
        }

        if (DocumentsUnionView::exists() && ! $this->option('force')) {
            $count = DB::table('documents')->count();
            $this->info("documents view already exists ({$count} rows). Use --force to recreate.");

            return self::SUCCESS;
        }

        $this->info('Creating documents union view...');
        DocumentsUnionView::recreate();

        $count = DB::table('documents')->count();
        $this->info("documents view ready ({$count} rows).");

        return self::SUCCESS;
    }
}
