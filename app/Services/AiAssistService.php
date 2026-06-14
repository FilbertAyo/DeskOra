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
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Please enter some text before using AI assist.');
        }

        $action = $this->normalizeAction($action);
        $systemPrompt = $this->systemPromptFor($action);
        $model = config('services.gemini.model', 'gemini-2.0-flash');
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
                            ['text' => $text],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.5,
                    'maxOutputTokens' => (int) config('services.gemini.max_tokens', 500),
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
                throw new RuntimeException('AI could not process this text. Please rephrase and try again.');
            }

            throw new RuntimeException('AI returned an empty response. Please try again.');
        }

        return [
            'action' => $action,
            'text' => $result,
        ];
    }

    private function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));

        if (! in_array($action, ['improve', 'expand'], true)) {
            throw new RuntimeException('Unsupported AI action.');
        }

        return $action;
    }

    private function systemPromptFor(string $action): string
    {
        return match ($action) {
            'improve' => 'You help staff write clear, professional workplace reports and plans. '
                .'Improve the user text for clarity and tone. Keep the same meaning and facts. '
                .'Do not invent details. Return only the improved text.',
            'expand' => 'You help staff write clear workplace reports and plans. '
                .'Expand the user notes into fuller, professional sentences. '
                .'Do not invent facts that were not implied. Return only the expanded text.',
        };
    }
}
