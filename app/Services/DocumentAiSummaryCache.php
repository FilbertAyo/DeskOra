<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DocumentAiSummaryCache
{
    private bool $lastSummaryWasCached = false;

    private bool $lastAnswerWasCached = false;

    public function rememberSummary(?Document $plan, ?Document $report, callable $generator): string
    {
        $wasCached = false;
        $value = $this->remember(
            $this->summaryCacheKey($plan, $report),
            $generator,
            $wasCached
        );
        $this->lastSummaryWasCached = $wasCached;

        return $value;
    }

    public function rememberAnswer(?Document $plan, ?Document $report, string $question, callable $generator): string
    {
        $wasCached = false;
        $value = $this->remember(
            $this->answerCacheKey($plan, $report, $question),
            $generator,
            $wasCached
        );
        $this->lastAnswerWasCached = $wasCached;

        return $value;
    }

    public function wasLastSummaryCached(): bool
    {
        return $this->lastSummaryWasCached;
    }

    public function wasLastAnswerCached(): bool
    {
        return $this->lastAnswerWasCached;
    }

    private function remember(string $cacheKey, callable $generator, bool &$wasCached): string
    {
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $wasCached = true;

            return $cached;
        }

        if (! $this->isEnabled()) {
            return (string) $generator();
        }

        $lock = Cache::lock('lock:'.$cacheKey, 30);

        try {
            $lock->block(15);

            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $wasCached = true;

                return $cached;
            }

            $value = (string) $generator();
            Cache::put($cacheKey, $value, $this->ttlSeconds());

            return $value;
        } catch (LockTimeoutException) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $wasCached = true;

                return $cached;
            }

            throw new RuntimeException('AI is busy generating a response. Please try again in a few seconds.');
        } finally {
            $lock->release();
        }
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.groq.summary_cache_enabled', true);
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('services.groq.summary_cache_seconds', 604800));
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
