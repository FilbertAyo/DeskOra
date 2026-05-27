<?php

/**
 * Standalone fix for missing `documents` view on production.
 * Does not require the documents:ensure-union-view artisan command.
 *
 * Usage (from repo root on server):
 *   php fix-documents-view.php
 */

declare(strict_types=1);

$autoload = __DIR__.'/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(STDERR, "Run from Laravel root after composer install.\n");
    exit(1);
}

require $autoload;

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$required = ['weekly_plans', 'weekly_reports', 'monthly_reports', 'action_logs'];
foreach ($required as $table) {
    if (! Illuminate\Support\Facades\Schema::hasTable($table)) {
        fwrite(STDERR, "Missing table: {$table}. Run domain migrations first.\n");
        exit(1);
    }
}

App\Database\DocumentsUnionView::recreate();
$count = Illuminate\Support\Facades\DB::table('documents')->count();

echo "documents view created ({$count} rows).\n";
