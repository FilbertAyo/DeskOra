<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Cache;

class DocumentAiSummaryCache
{
    public function rememberSummary(?Document $plan, ?Document $report, callable $generator): string
    {
        if (! $this->isEnabled()) {
            return (string) $generator();
        }

        return (string) Cache::remember(
            $this->summaryCacheKey($plan, $report),
            $this->ttlSeconds(),
            $generator
        );
    }

    public function rememberAnswer(?Document $plan, ?Document $report, string $question, callable $generator): string
    {
        if (! $this->isEnabled()) {
            return (string) $generator();
        }

        return (string) Cache::remember(
            $this->answerCacheKey($plan, $report, $question),
            $this->ttlSeconds(),
            $generator
        );
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.gemini.summary_cache_enabled', true);
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('services.gemini.summary_cache_seconds', 604800));
    }

    private function summaryCacheKey(?Document $plan, ?Document $report): string
    {
        return 'ai_weekly_summary:'.$this->submissionFingerprint($plan, $report);
    }

    private function answerCacheKey(?Document $plan, ?Document $report, string $question): string
    {
        $normalizedQuestion = mb_strtolower(trim($question));

        return 'ai_weekly_answer:'.$this->submissionFingerprint($plan, $report).':'.sha1($normalizedQuestion);
    }

    private function submissionFingerprint(?Document $plan, ?Document $report): string
    {
        return hash('sha256', json_encode([
            'plan_id' => $plan?->id,
            'plan_updated_at' => $plan?->updated_at?->toIso8601String(),
            'report_id' => $report?->id,
            'report_updated_at' => $report?->updated_at?->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    }
}
