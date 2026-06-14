<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiAssistService
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * @return array{action: string, text: string}
     */
    public function assist(string $text, string $action): array
    {
        $text = $this->requireText($text);
        $action = $this->normalizeWritingAction($action);

        return [
            'action' => $action,
            'text' => $this->generate($this->writingPromptFor($action), $text),
        ];
    }

    public function summarizeWeeklySubmission(string $text, bool $hasPlan, bool $hasReport): string
    {
        $text = $this->requireText($text);

        return $this->generate(
            $this->weeklySubmissionSummaryPrompt($hasPlan, $hasReport),
            $text,
            (int) config('services.gemini.summary_max_tokens', 800),
            formatAsSummary: true
        );
    }

    public function answerWeeklySubmissionQuestion(string $sourceText, string $question, ?string $summary = null): string
    {
        $sourceText = $this->requireText($sourceText);
        $question = trim($question);

        if ($question === '') {
            throw new RuntimeException('Please enter a question.');
        }

        $prompt = $this->plainTextInstruction()
            .'You answer staff questions about a weekly plan and weekly report. '
            .'Reply in 1 to 3 short, clear paragraphs. Use only facts from the documents provided. '
            .'If the answer is not in the plan or report, say clearly that the information is not available there. '
            .'Do not invent facts.';

        $userMessage = "Weekly plan and report data:\n\n".$sourceText;

        if (filled($summary)) {
            $userMessage .= "\n\nSummary already shown to the reader:\n\n".trim($summary);
        }

        $userMessage .= "\n\nQuestion from staff:\n".$question;

        return $this->generate(
            $prompt,
            $userMessage,
            (int) config('services.gemini.question_max_tokens', 400),
            formatAsSummary: true
        );
    }

    private function sanitizePlainText(string $text): string
    {
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text) ?? $text;
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '$1', $text) ?? $text;
        $text = preg_replace('/_([^_]+)_/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;

        return trim($text);
    }

    private function plainTextInstruction(): string
    {
        return 'Use plain text only. Do not use markdown, asterisks, hashtags, or other special formatting. ';
    }

    private function requireText(string $text): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('There is no text to process.');
        }

        return $text;
    }

    private function generate(string $systemPrompt, string $userText, ?int $maxTokens = null, bool $formatAsSummary = false): string
    {
        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model
        );

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => config('services.gemini.api_key'),
        ])
            ->timeout((int) config('services.gemini.timeout', 30))
            ->post($url, [
                'system_instruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $userText],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => $maxTokens ?? (int) config('services.gemini.max_tokens', 500),
                ],
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? 'AI request failed. Please try again.';

            throw new RuntimeException($message);
        }

        $result = trim((string) $response->json('candidates.0.content.parts.0.text', ''));

        if ($result === '') {
            $blockReason = $response->json('promptFeedback.blockReason');
            if ($blockReason) {
                throw new RuntimeException('AI could not process this document. Please try again later.');
            }

            throw new RuntimeException('AI returned an empty response. Please try again.');
        }

        return $formatAsSummary
            ? $this->formatSummaryAsParagraphs($result)
            : $this->sanitizePlainText($result);
    }

    private function formatSummaryAsParagraphs(string $text): string
    {
        $text = $this->sanitizePlainText($text);
        $text = preg_replace('/^\s*[-•]\s+/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function narrativeSummaryInstruction(): string
    {
        return 'Write the summary as a short, easy-to-read story in connected paragraphs. '
            .'Take the reader from A to Z: start with the weekly focus and plan, move through what was done, '
            .'then finish with results and an overall conclusion. '
            .'Use 3 to 5 short paragraphs. Separate each paragraph with one blank line. '
            .'Do not use bullet points, numbered lists, or section labels. ';
    }

    private function normalizeWritingAction(string $action): string
    {
        $action = strtolower(trim($action));

        if (! in_array($action, ['improve', 'expand'], true)) {
            throw new RuntimeException('Unsupported AI action.');
        }

        return $action;
    }

    private function writingPromptFor(string $action): string
    {
        $plainText = $this->plainTextInstruction();

        return match ($action) {
            'improve' => $plainText
                .'You help staff write clear, professional workplace reports and plans. '
                .'Improve the user text for clarity and tone. Keep the same meaning and facts. '
                .'Do not invent details. Return only the improved text.',
            'expand' => $plainText
                .'You help staff write clear workplace reports and plans. '
                .'Expand the user notes into fuller, professional sentences. '
                .'Do not invent facts that were not implied. Return only the expanded text.',
        };
    }

    private function weeklySubmissionSummaryPrompt(bool $hasPlan, bool $hasReport): string
    {
        $plainText = $this->plainTextInstruction();
        $narrative = $this->narrativeSummaryInstruction();

        if ($hasPlan && $hasReport) {
            return $plainText.$narrative
                .'You are summarizing a staff member\'s weekly plan and weekly report for someone who was not in the meetings. '
                .'Tell the story of the week: what they set out to do, what they actually did, what went well, what was challenging, and how the week ended overall. '
                .'Compare the plan with the report naturally inside the story. Do not invent facts. Keep it under 280 words.';
        }

        if ($hasPlan) {
            return $plainText.$narrative
                .'You are summarizing a weekly work plan. The report is not available yet. '
                .'Tell the story of what this person intends to do this week, including objectives, activities, and meetings. '
                .'Gently note that results are not yet reported. Keep it under 200 words.';
        }

        return $plainText.$narrative
            .'You are summarizing a weekly work report. The matching plan is not available. '
            .'Tell the story of what happened during the week, including progress, achievements, and challenges. '
            .'Keep it under 200 words.';
    }
}
