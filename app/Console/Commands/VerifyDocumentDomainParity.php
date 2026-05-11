<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\MonthlyReport;
use App\Models\WeeklyPlan;
use App\Models\WeeklyReport;
use Illuminate\Console\Command;

class VerifyDocumentDomainParity extends Command
{
    protected $signature = 'documents:verify-domain-parity {--sample=20 : Number of random sample records per type to inspect}';

    protected $description = 'Verify legacy document parity against normalized domain tables';

    public function handle(): int
    {
        $sample = max((int) $this->option('sample'), 1);
        $failed = false;

        $pairs = [
            ['type' => Document::TYPE_WEEKLY_PLAN, 'domain' => WeeklyPlan::class],
            ['type' => Document::TYPE_WEEKLY_REPORT, 'domain' => WeeklyReport::class],
            ['type' => Document::TYPE_MONTHLY_REPORT, 'domain' => MonthlyReport::class],
            ['type' => Document::TYPE_ACTION_LOG, 'domain' => ActionLog::class],
        ];

        $rows = [];
        foreach ($pairs as $pair) {
            $legacyCount = Document::query()->where('type', $pair['type'])->count();
            $domainCount = $pair['domain']::query()->count();
            $rows[] = [
                'type' => $pair['type'],
                'legacy_count' => (string) $legacyCount,
                'domain_count' => (string) $domainCount,
                'match' => $legacyCount === $domainCount ? 'yes' : 'no',
            ];
            if ($legacyCount !== $domainCount) {
                $failed = true;
            }
        }
        $this->table(['type', 'legacy_count', 'domain_count', 'match'], $rows);

        foreach ($pairs as $pair) {
            $missing = Document::query()
                ->where('type', $pair['type'])
                ->whereNotIn('id', $pair['domain']::query()->pluck('legacy_document_id'))
                ->count();
            if ($missing > 0) {
                $failed = true;
                $this->error("Missing {$pair['type']} mappings: {$missing}");
            }
        }

        $this->line('');
        $this->info('Sample checks:');
        foreach ($pairs as $pair) {
            $sampled = Document::query()
                ->where('type', $pair['type'])
                ->inRandomOrder()
                ->limit($sample)
                ->pluck('id')
                ->all();

            $domainMapped = $pair['domain']::query()
                ->whereIn('legacy_document_id', $sampled)
                ->pluck('legacy_document_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            $sampleMisses = array_values(array_diff(array_map('strval', $sampled), $domainMapped));
            if ($sampleMisses !== []) {
                $failed = true;
                $this->error("Sample misses for {$pair['type']}: ".implode(', ', $sampleMisses));
            } else {
                $this->info("{$pair['type']}: sampled {$sample} / no misses");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
