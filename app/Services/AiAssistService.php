<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class AiAssistService
{
    public function __construct(
        private ?PortalNavigationIntelligenceService $navigationIntelligence = null,
        private ?PortalAiContextResolver $contextResolver = null,
        private ?WeeklyDocumentFormKnowledgeService $formKnowledge = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.groq.api_key'));
    }

    /**
     * Draft weekly-report fields from the user's action-log tasks.
     * Always returns a usable payload; AI is optional (fail-open).
     *
     * @return array{
     *     available: bool,
     *     used_ai: bool,
     *     message: ?string,
     *     draft: array{
     *         summary: string,
     *         achievements: list<string>,
     *         challenges: list<array{challenge: string, action_taken_planned: string}>
     *     }
     * }
     */
    public function draftWeeklyReportFromActionTasks(User $user, ?string $periodId = null): array
    {
        $period = $periodId
            ? WorkPeriod::query()->find($periodId)
            : WorkPeriod::getCurrent();

        $tasks = $period
            ? $this->collectUserActionTasksForPeriod($user, $period)
            : [];

        if ($tasks === []) {
            return [
                'available' => $this->isConfigured(),
                'used_ai' => false,
                'message' => 'No action tasks found for this period to draft from.',
                'draft' => [
                    'summary' => '',
                    'achievements' => [],
                    'challenges' => [],
                ],
            ];
        }

        $heuristic = $this->heuristicWeeklyReportDraft($tasks, $period);

        if (! $this->isConfigured()) {
            return [
                'available' => false,
                'used_ai' => false,
                'message' => 'AI currently unavailable. A basic draft was built from your action tasks.',
                'draft' => $heuristic,
            ];
        }

        try {
            $snapshot = $this->formatActionTasksForPrompt($tasks, $period);
            $raw = $this->generate(
                $this->plainTextInstruction()
                    .'You help staff draft a weekly report from their action-log tasks. '
                    .'Return ONLY valid JSON with keys: summary (string), achievements (array of strings), '
                    .'challenges (array of objects with challenge and action_taken_planned strings). '
                    .'Use only facts from the tasks. Keep summary to 2-4 sentences. '
                    .'Include at most 5 achievements and 4 challenges. Do not invent work that is not listed.',
                "Staff action tasks for the week:\n\n".$snapshot,
                (int) config('services.groq.assistant_max_tokens', 700),
                formatAsSummary: false
            );

            $parsed = $this->parseWeeklyReportDraftJson($raw);
            if ($parsed === null) {
                return [
                    'available' => true,
                    'used_ai' => false,
                    'message' => 'AI response could not be used. A basic draft was built from your action tasks.',
                    'draft' => $heuristic,
                ];
            }

            return [
                'available' => true,
                'used_ai' => true,
                'message' => null,
                'draft' => $parsed,
            ];
        } catch (RuntimeException) {
            return [
                'available' => false,
                'used_ai' => false,
                'message' => 'AI currently unavailable. A basic draft was built from your action tasks.',
                'draft' => $heuristic,
            ];
        }
    }

    /**
     * @param  array{
     *     context_type: string,
     *     title?: string|null,
     *     details?: string|null,
     *     amount?: string|null,
     *     requester?: string|null,
     *     outcome?: string|null
     * }  $context
     */
    public function suggestRejectionReason(array $context): string
    {
        $type = $context['context_type'] ?? 'finance';
        $outcome = $context['outcome'] ?? 'rejected';

        $lines = [
            'Approval context type: '.$type,
            'Intended outcome: '.$outcome,
            'Title: '.trim((string) ($context['title'] ?? '')),
            'Requester: '.trim((string) ($context['requester'] ?? '')),
            'Amount: '.trim((string) ($context['amount'] ?? '')),
            'Details: '.trim((string) ($context['details'] ?? '')),
        ];

        $guidance = $outcome === 'resubmission'
            ? 'Write a short, professional request for resubmission that explains what must be corrected.'
            : 'Write a short, professional rejection reason that is clear and respectful.';

        return $this->generate(
            $this->plainTextInstruction()
                .'You help managers write approval feedback in DeskOra. '
                .$guidance
                .' Use 2 to 4 sentences. Do not invent missing facts. '
                .'Do not include greetings or sign-offs. Return only the comment text.',
            implode("\n", $lines),
            250,
            formatAsSummary: true
        );
    }

    /**
     * @return list<array{task: string, progress: string, notes: string}>
     */
    private function collectUserActionTasksForPeriod(User $user, WorkPeriod $period): array
    {
        $logs = ActionLog::query()
            ->where('period_id', $period->id)
            ->whereNull('superseded_by_action_log_id')
            ->where(function ($query) use ($user) {
                $query->where('state', Document::STATE_SUBMITTED)
                    ->orWhere('user_id', $user->id);
            })
            ->get(['id', 'user_id', 'state', 'period_id']);

        $rows = [];

        foreach ($logs as $log) {
            $document = Document::fromDomainId((string) $log->id);
            if (! $document) {
                continue;
            }

            foreach (ActionLogTaskSupport::extractTasks($document->content) as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $label = trim((string) ($task['task'] ?? ''));
                if ($label === '') {
                    continue;
                }

                $responsibleIds = ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
                $isOwner = (string) $log->user_id === (string) $user->id;
                $isAssignee = in_array((string) $user->id, $responsibleIds, true);

                if (! $isOwner && ! $isAssignee) {
                    continue;
                }

                $rows[] = [
                    'task' => $label,
                    'progress' => trim((string) ($task['progress'] ?? ActionLogTaskSupport::PROGRESS_NOT_STARTED)),
                    'notes' => trim((string) ($task['notes_progress'] ?? '')),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{task: string, progress: string, notes: string}>  $tasks
     * @return array{
     *     summary: string,
     *     achievements: list<string>,
     *     challenges: list<array{challenge: string, action_taken_planned: string}>
     * }
     */
    private function heuristicWeeklyReportDraft(array $tasks, ?WorkPeriod $period): array
    {
        $weekLabel = $period
            ? 'Week '.$period->week_number.' of '.$period->year
            : 'this week';

        $done = [];
        $active = [];
        $blocked = [];

        foreach ($tasks as $task) {
            $line = $task['task'];
            if ($task['notes'] !== '') {
                $line .= ' ('.$task['notes'].')';
            }

            if ($task['progress'] === ActionLogTaskSupport::PROGRESS_DONE) {
                $done[] = $task['task'];
            } elseif ($task['progress'] === ActionLogTaskSupport::PROGRESS_CANCELLED) {
                $blocked[] = [
                    'challenge' => $task['task'].' was cancelled.',
                    'action_taken_planned' => $task['notes'] !== '' ? $task['notes'] : 'Review and re-plan if still needed.',
                ];
            } else {
                $active[] = $line;
            }
        }

        $summaryParts = ["During {$weekLabel}, work focused on assigned action-log tasks."];
        if ($done !== []) {
            $summaryParts[] = 'Completed: '.implode('; ', array_slice($done, 0, 5)).'.';
        }
        if ($active !== []) {
            $summaryParts[] = 'Still in progress: '.implode('; ', array_slice($active, 0, 5)).'.';
        }

        return [
            'summary' => implode(' ', $summaryParts),
            'achievements' => array_slice($done, 0, 5),
            'challenges' => array_slice($blocked !== [] ? $blocked : (
                $active !== []
                    ? [[
                        'challenge' => 'Some assigned tasks remain unfinished.',
                        'action_taken_planned' => 'Continue follow-up on: '.implode('; ', array_slice(
                            array_map(fn ($t) => $t['task'], array_filter($tasks, fn ($t) => ! in_array($t['progress'], ['Done', 'Cancelled'], true))),
                            0,
                            3
                        )).'.',
                    ]]
                    : []
            ), 0, 4),
        ];
    }

    /**
     * @param  list<array{task: string, progress: string, notes: string}>  $tasks
     */
    private function formatActionTasksForPrompt(array $tasks, ?WorkPeriod $period): string
    {
        $lines = [];
        if ($period) {
            $lines[] = 'Period: Week '.$period->week_number.' of '.$period->year.' ('.$period->date_range.')';
        }

        foreach ($tasks as $index => $task) {
            $lines[] = sprintf(
                '%d. [%s] %s%s',
                $index + 1,
                $task['progress'],
                $task['task'],
                $task['notes'] !== '' ? ' — notes: '.$task['notes'] : ''
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{
     *     summary: string,
     *     achievements: list<string>,
     *     challenges: list<array{challenge: string, action_taken_planned: string}>
     * }|null
     */
    private function parseWeeklyReportDraftJson(string $raw): ?array
    {
        $raw = trim($raw);
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            $raw = $matches[0];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        $achievements = [];
        foreach (($decoded['achievements'] ?? []) as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $achievements[] = $text;
            }
        }

        $challenges = [];
        foreach (($decoded['challenges'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $challenge = trim((string) ($item['challenge'] ?? ''));
            $action = trim((string) ($item['action_taken_planned'] ?? ''));
            if ($challenge === '' && $action === '') {
                continue;
            }
            $challenges[] = [
                'challenge' => $challenge,
                'action_taken_planned' => $action,
            ];
        }

        if ($summary === '' && $achievements === [] && $challenges === []) {
            return null;
        }

        return [
            'summary' => $summary,
            'achievements' => array_values(array_slice($achievements, 0, 5)),
            'challenges' => array_values(array_slice($challenges, 0, 4)),
        ];
    }

    public function summarizeWeeklySubmission(string $text, bool $hasPlan, bool $hasReport): string
    {
        $text = $this->requireText($text);

        return $this->generate(
            $this->weeklySubmissionSummaryPrompt($hasPlan, $hasReport),
            $text,
            (int) config('services.groq.summary_max_tokens', 800),
            formatAsSummary: true
        );
    }

    public function answerPortalQuestion(string $question, ?User $user = null): array
    {
        $question = trim($question);

        if ($question === '') {
            throw new RuntimeException('Please enter a question.');
        }

        if ($user !== null) {
            $this->acquireAssistantUserSlot($user);
        }

        $context = $user !== null
            ? $this->getContextResolver()->resolve($user, $question)
            : ['domains' => [], 'snapshot' => '', 'has_live_data' => false];

        $answer = $this->generate(
            $this->portalAssistantSystemPrompt($user, $context['has_live_data']),
            $this->portalAssistantUserMessage($question, $context),
            (int) config('services.groq.assistant_max_tokens', $context['has_live_data'] ? 700 : 600),
            formatAsSummary: true
        );

        return [
            'answer' => $answer,
            'has_live_data' => $context['has_live_data'],
        ];
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
            .'You may explain what each section or field means based on the data shown. '
            .'If asked what is missing or empty, list sections that have no content in the provided data. '
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
            (int) config('services.groq.question_max_tokens', 400),
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
            throw new RuntimeException('AI is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('There is no text to process.');
        }

        return $text;
    }

    private function generate(string $systemPrompt, string $userText, ?int $maxTokens = null, bool $formatAsSummary = false): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $model = config('services.groq.model', 'llama-3.3-70b-versatile');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $url = $baseUrl.'/chat/completions';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userText],
            ],
            'temperature' => 0.4,
            'max_tokens' => $maxTokens ?? (int) config('services.groq.max_tokens', 500),
        ];

        $this->acquireAiSlot();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.config('services.groq.api_key'),
        ])
            ->timeout((int) config('services.groq.timeout', 30))
            ->post($url, $payload);

        if ($response->successful()) {
            $result = trim((string) $response->json('choices.0.message.content', ''));

            if ($result === '') {
                throw new RuntimeException('AI returned an empty response. Please try again.');
            }

            return $formatAsSummary
                ? $this->formatSummaryAsParagraphs($result)
                : $this->sanitizePlainText($result);
        }

        $message = (string) ($response->json('error.message') ?? 'AI request failed. Please try again.');

        throw new RuntimeException($this->friendlyApiError($response->status(), $message));
    }

    private function acquireAiSlot(): void
    {
        $key = 'groq-api-global';
        $maxAttempts = max(1, (int) config('services.groq.max_requests_per_minute', 30));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw new RuntimeException("AI is busy. Please wait about {$seconds} seconds and try again.");
        }

        RateLimiter::hit($key, 60);
    }

    private function acquireAssistantUserSlot(User $user): void
    {
        $key = 'groq-assistant-user:'.$user->id;
        $maxAttempts = max(1, (int) config('services.groq.assistant_max_requests_per_minute', 10));

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            throw new RuntimeException("You've reached the AI assistant limit. Please wait about {$seconds} seconds and try again.");
        }

        RateLimiter::hit($key, 60);
    }

    private function portalAssistantSystemPrompt(?User $user, bool $hasLiveData = false): string
    {
        $plainText = $this->plainTextInstruction();

        $liveDataRules = $hasLiveData
            ? 'A LIVE DATA SNAPSHOT is included with the question. Answer the question directly with that data FIRST, using only the snapshot for facts, counts, statuses, and deadlines. '
                .'After answering, you may add at most one short sentence pointing to where in the portal the user can see more, using the menu path only. '
                .'If the snapshot contains a line beginning with ACCESS NOTE, it means the user does not have permission to view that information — tell them clearly and politely that they do not have access and may contact an administrator, and do not invent the data. '
            : 'No LIVE DATA SNAPSHOT is included, so you do not have the user\'s live records. Guide them using the navigation catalog and do not invent statuses, counts, or values. ';

        return $plainText
            .'You are Deskora AI, a helpful assistant for the DeskOra portal. '
            .'Staff use DeskOra for year plans, the weekly matrix (including impact entries), weekly and monthly reports, tasks assigned by teammates, documents, petty cash, action logs, and more. '
            .'Interpret staff questions and, when you point users to a page, use ONLY the navigation catalog below for menu paths. '
            .$liveDataRules
            .'When staff ask what to fill in on a weekly plan or weekly report, which sections exist, what a field means, or what is required, use the WEEKLY DOCUMENT FORM REFERENCE below. Do not invent fields. '
            .'Refer to pages by their menu path only. Never include a URL or web link in your answer. '
            .'Keep navigation as a brief pointer, not the main answer. '
            .'Answer in 1 to 3 short, clear paragraphs. Be practical and friendly. '
            .'Do not greet the user or open with their name, job title, or department. Do not say hello unless they greeted you first. '
            .'Only mention permissions or contacting an administrator when the snapshot includes an ACCESS NOTE indicating the user lacks permission; otherwise do not add permission disclaimers. '
            .'If you do not know a specific policy or data value, say so honestly. '
            .'Do not invent specific numbers, names, approval statuses, or menu paths. '
            .'Do not claim you can see records that were not provided in a LIVE DATA SNAPSHOT. '
            ."\n\n"
            .$this->getNavigationIntelligence()->formatPromptContext($user)
            ."\n\n"
            .$this->getFormKnowledge()->formatPromptContext();
    }

    private function getNavigationIntelligence(): PortalNavigationIntelligenceService
    {
        return $this->navigationIntelligence ??= app(PortalNavigationIntelligenceService::class);
    }

    private function getContextResolver(): PortalAiContextResolver
    {
        return $this->contextResolver ??= app(PortalAiContextResolver::class);
    }

    private function getFormKnowledge(): WeeklyDocumentFormKnowledgeService
    {
        return $this->formKnowledge ??= app(WeeklyDocumentFormKnowledgeService::class);
    }

    /**
     * @param  array{domains: array<int, string>, snapshot: string, has_live_data: bool}  $context
     */
    private function portalAssistantUserMessage(string $question, array $context): string
    {
        $message = 'Question from staff member: '.$question;

        if (! empty($context['has_live_data']) && filled($context['snapshot'])) {
            $message = "LIVE DATA SNAPSHOT (pre-processed by the portal backend — use only this for factual answers about the user's records):\n"
                .$context['snapshot']
                ."\n\n"
                .$message;
        }

        return $message;
    }

    private function friendlyApiError(int $status, string $message): string
    {
        $normalized = strtolower($message);

        if (str_contains($normalized, 'invalid api key') || str_contains($normalized, 'invalid authentication')) {
            return 'AI is not configured correctly. Set a valid GROQ_API_KEY (starts with gsk_) in .env, then run php artisan config:clear.';
        }

        if ($status === 429 || str_contains($normalized, 'rate limit') || str_contains($normalized, 'quota')) {
            if (preg_match('/try again in ([0-9.]+)\s*s/i', $message, $matches)
                || preg_match('/retry in ([0-9.]+)s/i', $message, $matches)) {
                $seconds = (int) ceil((float) $matches[1]);

                return "AI free tier limit reached. Please wait about {$seconds} seconds and try again.";
            }

            return 'AI free tier limit reached. Please wait about a minute and try again.';
        }

        return $message;
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
