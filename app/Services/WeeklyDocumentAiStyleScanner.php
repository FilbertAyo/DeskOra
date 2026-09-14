<?php

namespace App\Services;

use App\Models\Document;

class WeeklyDocumentAiStyleScanner
{
    /**
     * @var list<string>
     */
    private const AI_PHRASES = [
        'a testament to',
        'as we navigate',
        'at the end of the day',
        'cutting-edge',
        'delve',
        'embark on',
        'ever-evolving',
        'foster an environment',
        'furthermore',
        'game-changer',
        'holistic approach',
        'in conclusion',
        'in today\'s',
        'in the realm',
        'it is important to note',
        'it is worth noting',
        'it\'s important to note',
        'landscape of',
        'leverage our',
        'meticulous',
        'moreover',
        'moving forward',
        'plays a crucial role',
        'plays a pivotal role',
        'plays a vital role',
        'plethora',
        'realm of',
        'serve as a',
        'showcases',
        'streamline',
        'tapestry',
        'underscore',
        'underscoring',
    ];

    public function __construct(
        private readonly AiAssistService $aiAssist,
    ) {}

    /**
     * @return array{
     *     document_type: string,
     *     available: bool,
     *     used_ai: bool,
     *     advisory: true,
     *     word_count: int,
     *     human_percent: ?int,
     *     ai_percent: ?int,
     *     label: string,
     *     signals: list<string>,
     *     message: ?string
     * }
     */
    public function scan(string $documentType, string $text): array
    {
        $documentType = $this->normalizeDocumentType($documentType);
        $text = trim($text);
        $wordCount = $this->wordCount($text);
        $heuristic = $this->heuristicScan($text, $wordCount);
        $aiAssessment = $wordCount >= 12 ? $this->aiAssist->assessWeeklyWritingStyle($text) : null;

        $aiPercent = $heuristic['ai_percent'];
        $signals = $heuristic['signals'];
        $usedAi = $aiAssessment !== null;

        if ($aiAssessment !== null) {
            $aiPercent = (int) round(($heuristic['ai_percent'] * 0.45) + ($aiAssessment['ai_percent'] * 0.55));
            $signals = $this->mergeSignals($heuristic['signals'], $aiAssessment['signals']);
        }

        if ($wordCount < 12) {
            $aiPercent = null;
            $signals = [];
        }

        $humanPercent = $aiPercent === null ? null : max(0, min(100, 100 - $aiPercent));

        return [
            'document_type' => $documentType,
            'available' => $this->aiAssist->isConfigured(),
            'used_ai' => $usedAi,
            'advisory' => true,
            'word_count' => $wordCount,
            'human_percent' => $humanPercent,
            'ai_percent' => $aiPercent,
            'label' => $this->labelFor($aiPercent),
            'signals' => $signals,
            'message' => $wordCount < 12
                ? 'Not enough writing yet to estimate AI-like style.'
                : null,
        ];
    }

    public function extractFromDocument(Document $document, ?string $extra = null): string
    {
        $content = is_array($document->content) ? $document->content : [];
        $parts = [];

        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $parts[] = trim((string) $document->main_target);
            $parts = array_merge($parts, $this->planContentParts($content));
        } elseif ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $parts = array_merge($parts, $this->reportContentParts($content));
            $parts[] = trim((string) ($document->target_explanation ?? ''));
        }

        if (is_string($extra) && trim($extra) !== '') {
            $parts[] = trim($extra);
        }

        return $this->joinParts($parts);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public function planContentParts(array $content): array
    {
        $parts = [
            trim((string) ($content['objective']['reflection_progress'] ?? '')),
        ];

        foreach (is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = trim((string) ($row['activity'] ?? ''));
            $parts[] = trim((string) ($row['notes_inclusion'] ?? ''));
        }

        foreach (is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = trim((string) ($row['meeting'] ?? ''));
            $parts[] = trim((string) ($row['reactions_notes'] ?? ''));
        }

        foreach (is_array($content['budget'] ?? null) ? $content['budget'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = trim((string) ($row['item'] ?? ''));
            $parts[] = trim((string) ($row['description_purpose'] ?? ''));
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    public function reportContentParts(array $content): array
    {
        $parts = [
            trim((string) ($content['summary'] ?? '')),
        ];

        foreach (is_array($content['activity_progress'] ?? null) ? $content['activity_progress'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $activityOutput = trim((string) ($row['activity_output'] ?? ''));
            if ($activityOutput !== '' && $activityOutput !== '__off_plan__') {
                $parts[] = $activityOutput;
            }
            $parts[] = trim((string) ($row['off_plan_activity'] ?? ''));
            $parts[] = trim((string) ($row['remarks_challenges'] ?? ''));
            $parts[] = trim((string) ($row['next_step'] ?? ''));
        }

        foreach (is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = trim((string) ($row['meeting'] ?? ''));
            $parts[] = trim((string) ($row['reactions_notes'] ?? ''));
        }

        foreach (is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [] as $item) {
            if (is_string($item)) {
                $parts[] = trim($item);

                continue;
            }
            if (is_array($item)) {
                $parts[] = trim((string) ($item['achievement'] ?? ''));
            }
        }

        foreach (is_array($content['challenges_mitigation'] ?? null) ? $content['challenges_mitigation'] : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $parts[] = trim((string) ($row['challenge'] ?? ''));
            $parts[] = trim((string) ($row['action_taken_planned'] ?? ''));
        }

        return $parts;
    }

    /**
     * @return array{ai_percent: int, signals: list<string>}
     */
    private function heuristicScan(string $text, int $wordCount): array
    {
        if ($wordCount < 12) {
            return ['ai_percent' => 0, 'signals' => []];
        }

        $signals = [];
        $score = 8;

        $dashHits = $this->countAiDashes($text);
        if ($dashHits > 0) {
            $score += min(36, $dashHits * 12);
            $signals[] = 'Long dashes (—) often used by AI';
        }

        $phraseHits = $this->countAiPhrases($text);
        if ($phraseHits > 0) {
            $score += min(32, $phraseHits * 8);
            $signals[] = 'Wording that often appears in AI drafts';
        }

        $structure = $this->structureSignals($text);
        $score += $structure['score'];
        $signals = array_merge($signals, $structure['signals']);

        if (preg_match('/(^|\n)\s*([•*]|\d+\.)\s+\S/u', $text) === 1
            || str_contains($text, '**')) {
            $score += 8;
            $signals[] = 'Markdown or bullet formatting typical of AI output';
        }

        return [
            'ai_percent' => max(5, min(92, (int) round($score))),
            'signals' => array_values(array_unique($signals)),
        ];
    }

    private function countAiDashes(string $text): int
    {
        $withoutNumericRanges = preg_replace('/\d+\s*[–—―]\s*\d+/u', ' ', $text) ?? $text;
        preg_match_all('/\S+\s+[—–―]\s+\S+/u', $withoutNumericRanges, $matches);

        return count($matches[0] ?? []);
    }

    private function countAiPhrases(string $text): int
    {
        $normalized = mb_strtolower($text);
        $hits = 0;

        foreach (self::AI_PHRASES as $phrase) {
            if (str_contains($normalized, $phrase)) {
                $hits++;
            }
        }

        if (preg_match('/not only\b.{0,80}\bbut also\b/u', $normalized) === 1) {
            $hits++;
        }

        return $hits;
    }

    /**
     * @return array{score: int, signals: list<string>}
     */
    private function structureSignals(string $text): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $sentences = array_values(array_filter($sentences, fn ($sentence) => mb_strlen(trim((string) $sentence)) > 12));
        if (count($sentences) < 4) {
            return ['score' => 0, 'signals' => []];
        }

        $lengths = [];
        $evenOpeners = 0;
        foreach ($sentences as $sentence) {
            $words = $this->wordCount((string) $sentence);
            $lengths[] = $words;
            if (preg_match('/^(this|the|additionally|furthermore|moreover|overall|in)\b/i', trim((string) $sentence)) === 1) {
                $evenOpeners++;
            }
        }

        $count = count($lengths);
        $mean = array_sum($lengths) / $count;
        $variance = 0.0;
        foreach ($lengths as $length) {
            $variance += ($length - $mean) ** 2;
        }
        $stdDev = sqrt($variance / $count);

        $signals = [];
        $score = 0;

        if ($mean >= 22 && $stdDev <= 5.5) {
            $score += 16;
            $signals[] = 'Sentences have a very even, polished rhythm';
        } elseif ($mean >= 28) {
            $score += 8;
            $signals[] = 'Sentences are long and formal throughout';
        }

        if ($evenOpeners / $count >= 0.6) {
            $score += 8;
            $signals[] = 'Many sentences start the same formal way';
        }

        return ['score' => $score, 'signals' => $signals];
    }

    /**
     * @param  list<string>  $primary
     * @param  list<string>  $secondary
     * @return list<string>
     */
    private function mergeSignals(array $primary, array $secondary): array
    {
        $merged = [];
        foreach (array_merge($primary, $secondary) as $signal) {
            $label = trim((string) $signal);
            if ($label === '') {
                continue;
            }
            foreach ($merged as $existing) {
                if (mb_strtolower($existing) === mb_strtolower($label)) {
                    continue 2;
                }
            }
            $merged[] = $label;
            if (count($merged) >= 5) {
                break;
            }
        }

        return $merged;
    }

    private function labelFor(?int $aiPercent): string
    {
        if ($aiPercent === null) {
            return 'Not enough text to estimate';
        }
        if ($aiPercent < 25) {
            return 'Looks mostly human';
        }
        if ($aiPercent < 50) {
            return 'Some AI-like elements';
        }
        if ($aiPercent < 75) {
            return 'Noticeable AI-like writing';
        }

        return 'Looks heavily AI-assisted';
    }

    private function normalizeDocumentType(string $documentType): string
    {
        return match ($documentType) {
            Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT => $documentType,
            default => Document::TYPE_WEEKLY_PLAN,
        };
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinParts(array $parts): string
    {
        $cleaned = [];
        foreach ($parts as $part) {
            $text = trim((string) $part);
            if ($text !== '') {
                $cleaned[] = $text;
            }
        }

        return implode("\n\n", $cleaned);
    }

    private function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $text) ?: [];

        return count(array_filter($words, fn ($word) => $word !== ''));
    }
}
