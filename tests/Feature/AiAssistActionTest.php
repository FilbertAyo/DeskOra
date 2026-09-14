<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\AiAssistService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_status_reports_unavailable_without_api_key(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->getJson(route('ai-assist.status'))
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_draft_weekly_report_fails_open_with_heuristic_when_ai_down(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create([
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Week action log',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [[
                    'task_uid' => 'task-1',
                    'task_no' => 1,
                    'task' => 'Finalize partner outreach',
                    'progress' => 'Done',
                    'notes_progress' => 'Emails sent',
                    'responsible_user_ids' => [(string) $user->id],
                ]],
            ],
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-assist.draft-weekly-report'), [
            'period_id' => $period->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('used_ai', false);
        $this->assertStringContainsString('Finalize partner outreach', (string) $response->json('draft.summary'));
        $this->assertContains('Finalize partner outreach', $response->json('draft.achievements'));
    }

    public function test_suggest_rejection_returns_unavailable_without_blocking_when_ai_down(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.suggest-rejection'), [
                'context_type' => 'finance',
                'title' => 'Office supplies',
                'outcome' => 'rejected',
            ])
            ->assertOk()
            ->assertJson([
                'available' => false,
                'suggestion' => null,
            ]);
    }

    public function test_suggest_rejection_returns_text_when_ai_succeeds(): void
    {
        config([
            'services.groq.api_key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'The requested amount exceeds the approved weekly budget. Please revise the quantity and resubmit.',
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.suggest-rejection'), [
                'context_type' => 'finance',
                'title' => 'Laptop purchase',
                'amount' => '3500000',
                'outcome' => 'rejected',
            ])
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('suggestion', 'The requested amount exceeds the approved weekly budget. Please revise the quantity and resubmit.');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return ($data['model'] ?? null) === 'openai/gpt-oss-120b'
                && array_key_exists('max_completion_tokens', $data)
                && ($data['reasoning_effort'] ?? null) === 'low';
        });
    }

    public function test_retired_groq_model_is_mapped_to_replacement(): void
    {
        config([
            'services.groq.api_key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'llama-3.3-70b-versatile',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => 'Please attach receipts and resubmit with the approved amount.',
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.suggest-rejection'), [
                'context_type' => 'finance',
                'title' => 'Laptop purchase',
                'amount' => '3500000',
                'outcome' => 'rejected',
            ])
            ->assertOk()
            ->assertJsonPath('available', true);

        Http::assertSent(fn ($request) => ($request->data()['model'] ?? null) === 'openai/gpt-oss-120b');
    }

    public function test_service_heuristic_draft_handles_empty_tasks(): void
    {
        config(['services.groq.api_key' => '']);
        $user = User::factory()->create(['status' => 'active']);

        $result = app(AiAssistService::class)->draftWeeklyReportFromActionTasks($user, null);

        $this->assertFalse($result['used_ai']);
        $this->assertSame('', $result['draft']['summary']);
        $this->assertSame([], $result['draft']['activity_progress']);
    }

    public function test_polish_performance_tips_fails_open_without_api_key(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.polish-performance-tips'), [
                'tips' => [
                    'You missed 1 weekly report in July.',
                    'Work-log consistency is below 60% for July.',
                ],
                'metrics' => ['overall_score' => 42],
            ])
            ->assertOk()
            ->assertJsonPath('used_ai', false)
            ->assertJsonPath('available', false)
            ->assertJsonPath('tips.0', 'You missed 1 weekly report in July.');
    }

    public function test_polish_performance_tips_returns_api_error_message(): void
    {
        config([
            'services.groq.api_key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Invalid API Key'],
            ], 401),
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.polish-performance-tips'), [
                'tips' => ['Keep submitting weekly reports on time.'],
            ])
            ->assertOk()
            ->assertJsonPath('used_ai', false)
            ->assertJsonPath('available', true)
            ->assertJsonFragment(['message' => 'AI is not configured correctly. Set a valid GROQ_API_KEY (starts with gsk_) in .env, then run php artisan config:clear.']);
    }

    public function test_draft_weekly_report_fills_activity_progress_from_work_logs(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create([
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        \App\Models\WeeklyWorkLog::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'log_date' => now()->toDateString(),
            'done' => 'Visited partner site',
            'blocker' => 'Waiting on clearance',
            'next' => 'Follow up tomorrow',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai-assist.draft-weekly-report'), [
            'period_id' => $period->id,
            'activity_progress' => [[
                'activity_output' => 'Monday - Partner visit',
                'progress' => '',
                'remarks_challenges' => '',
                'next_step' => '',
            ]],
        ]);

        $response->assertOk();
        $response->assertJsonPath('used_ai', false);
        $this->assertNotEmpty($response->json('draft.activity_progress'));
        $this->assertSame('Monday - Partner visit', $response->json('draft.activity_progress.0.activity_output'));
        $this->assertContains('Visited partner site', $response->json('draft.achievements'));
    }

    public function test_scan_weekly_writing_is_advisory_and_fails_open_without_api_key(): void
    {
        config(['services.groq.api_key' => '']);

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->postJson(route('ai-assist.scan-weekly-writing'), [
            'document_type' => 'weekly_report',
            'text' => 'This week we will delve into the landscape of partner work — a testament to meticulous planning. Furthermore, it is important to note this tapestry of delivery.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('advisory', true);
        $response->assertJsonPath('used_ai', false);
        $response->assertJsonPath('available', false);
        $this->assertGreaterThanOrEqual(50, $response->json('ai_percent'));
        $this->assertSame(100 - (int) $response->json('ai_percent'), $response->json('human_percent'));
    }

    public function test_scan_weekly_writing_blends_groq_score_when_available(): void
    {
        config([
            'services.groq.api_key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'services.groq.model' => 'openai/gpt-oss-120b',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => '{"ai_percent":80,"signals":["em dashes","formal AI wording"]}',
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user)->postJson(route('ai-assist.scan-weekly-writing'), [
            'document_type' => 'weekly_plan',
            'text' => 'Finish partner visits on Tuesday and print the remaining books for the field team after the office meeting with finance.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('advisory', true);
        $response->assertJsonPath('used_ai', true);
        $response->assertJsonPath('available', true);
        $this->assertGreaterThan(8, $response->json('ai_percent'));
        $this->assertContains('em dashes', $response->json('signals'));
        Http::assertSent(fn ($request) => ($request->data()['temperature'] ?? null) === 0.1);
    }

    public function test_scan_weekly_writing_still_returns_heuristic_when_groq_fails(): void
    {
        config([
            'services.groq.api_key' => 'gsk_test_key',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
        ]);

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Invalid API Key'],
            ], 401),
        ]);

        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->postJson(route('ai-assist.scan-weekly-writing'), [
                'document_type' => 'weekly_plan',
                'text' => 'Visit the remaining schools on Thursday and collect signed forms from the head teachers before Friday.',
            ])
            ->assertOk()
            ->assertJsonPath('advisory', true)
            ->assertJsonPath('used_ai', false)
            ->assertJsonPath('available', true);
    }

    public function test_weekly_report_fields_no_longer_offer_draft_with_ai(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag);

        $html = view('documents.partials.create.weekly-report-fields', [
            'relatedPlan' => null,
            'reportMeetingRows' => [],
            'teamUsers' => collect(),
        ])->render();

        $this->assertStringNotContainsString('Draft with AI', $html);
        $this->assertStringNotContainsString('data-ai-draft-weekly-report', $html);
    }

    public function test_submit_scan_modal_is_advisory_and_covers_all_sections(): void
    {
        $html = view('documents.partials.weekly-submit-scan-modal')->render();

        $this->assertStringContainsString('id="weeklySubmitScanModal"', $html);
        $this->assertStringContainsString('Checking writing style across all sections', $html);
        $this->assertStringContainsString('Guide only', $html);
        $this->assertStringContainsString('weeklySubmitScanConfirmBtn', $html);
    }
}
