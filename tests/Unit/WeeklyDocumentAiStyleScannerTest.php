<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Services\WeeklyDocumentAiStyleScanner;
use Tests\TestCase;

class WeeklyDocumentAiStyleScannerTest extends TestCase
{
    public function test_human_notes_score_low_ai_percent(): void
    {
        config(['services.groq.api_key' => '']);

        $result = app(WeeklyDocumentAiStyleScanner::class)->scan(
            Document::TYPE_WEEKLY_PLAN,
            'Finish partner visits on Tuesday. Print the books for the field team. Follow up with finance on the remaining receipts after the meeting in the office.'
        );

        $this->assertTrue($result['advisory']);
        $this->assertFalse($result['used_ai']);
        $this->assertSame(Document::TYPE_WEEKLY_PLAN, $result['document_type']);
        $this->assertNotNull($result['ai_percent']);
        $this->assertLessThan(25, $result['ai_percent']);
        $this->assertSame(100 - $result['ai_percent'], $result['human_percent']);
        $this->assertSame('Looks mostly human', $result['label']);
    }

    public function test_em_dashes_and_ai_wording_raise_score(): void
    {
        config(['services.groq.api_key' => '']);

        $result = app(WeeklyDocumentAiStyleScanner::class)->scan(
            Document::TYPE_WEEKLY_REPORT,
            'This week we will delve into the landscape of partner engagement — a testament to our meticulous planning. '
            .'Furthermore, we will foster an environment of collaboration — showcasing a holistic approach. '
            .'It is important to note that this tapestry of activities plays a crucial role as we navigate the realm of delivery.'
        );

        $this->assertGreaterThanOrEqual(50, $result['ai_percent']);
        $this->assertNotEmpty($result['signals']);
        $this->assertTrue(collect($result['signals'])->contains(
            fn ($signal) => str_contains(strtolower((string) $signal), 'dash')
        ));
    }

    public function test_short_text_is_still_submittable_without_a_score(): void
    {
        config(['services.groq.api_key' => '']);

        $result = app(WeeklyDocumentAiStyleScanner::class)->scan(
            Document::TYPE_WEEKLY_PLAN,
            'Print books'
        );

        $this->assertNull($result['ai_percent']);
        $this->assertNull($result['human_percent']);
        $this->assertTrue($result['advisory']);
        $this->assertStringContainsString('still submit', (string) $result['message']);
    }

    public function test_extracts_all_plan_sections_including_main_target(): void
    {
        $document = new Document([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'main_target' => 'Reach 12 schools this week',
            'content' => [
                'objective' => ['reflection_progress' => 'Last week we reached 9 schools'],
                'planned_activities' => [
                    ['activity' => 'Visit Ilala schools', 'notes_inclusion' => 'Bring printed forms'],
                ],
                'key_meetings' => [
                    ['meeting' => 'Head teacher briefing', 'reactions_notes' => 'Confirm attendance list'],
                ],
                'budget' => [
                    ['item' => 'Transport', 'description_purpose' => 'Field visits in Ilala'],
                ],
            ],
        ]);

        $text = app(WeeklyDocumentAiStyleScanner::class)->extractFromDocument($document);

        $this->assertStringContainsString('Reach 12 schools this week', $text);
        $this->assertStringContainsString('Last week we reached 9 schools', $text);
        $this->assertStringContainsString('Visit Ilala schools', $text);
        $this->assertStringContainsString('Bring printed forms', $text);
        $this->assertStringContainsString('Head teacher briefing', $text);
        $this->assertStringContainsString('Field visits in Ilala', $text);
    }

    public function test_extracts_all_report_sections(): void
    {
        $document = new Document([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'target_explanation' => 'Rain delayed two visits',
            'content' => [
                'summary' => 'The week focused on school visits.',
                'activity_progress' => [[
                    'activity_output' => 'Monday - Visit schools',
                    'remarks_challenges' => 'Two schools were closed',
                    'next_step' => 'Return next week',
                ]],
                'key_meetings' => [[
                    'meeting' => 'Cluster meeting',
                    'reactions_notes' => 'Agreed on the new timetable',
                ]],
                'key_achievements' => ['Distributed 40 books'],
                'challenges_mitigation' => [[
                    'challenge' => 'Transport shortage',
                    'action_taken_planned' => 'Share a vehicle with the team',
                ]],
            ],
        ]);

        $text = app(WeeklyDocumentAiStyleScanner::class)->extractFromDocument($document);

        $this->assertStringContainsString('The week focused on school visits.', $text);
        $this->assertStringContainsString('Two schools were closed', $text);
        $this->assertStringContainsString('Return next week', $text);
        $this->assertStringContainsString('Cluster meeting', $text);
        $this->assertStringContainsString('Distributed 40 books', $text);
        $this->assertStringContainsString('Transport shortage', $text);
        $this->assertStringContainsString('Rain delayed two visits', $text);
    }
}
