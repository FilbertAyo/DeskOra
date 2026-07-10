<?php

namespace Tests\Feature;

use App\Services\WeeklyDocumentFormKnowledgeService;
use Tests\TestCase;

class WeeklyDocumentFormKnowledgeServiceTest extends TestCase
{
    public function test_format_prompt_context_includes_weekly_plan_sections_and_required_fields(): void
    {
        $text = app(WeeklyDocumentFormKnowledgeService::class)->formatPromptContext();

        $this->assertStringContainsString('WEEKLY DOCUMENT FORM REFERENCE', $text);
        $this->assertStringContainsString('WEEKLY PLAN:', $text);
        $this->assertStringContainsString('Key Objective for the Week', $text);
        $this->assertStringContainsString('Objective(s) / Main Target(s)', $text);
        $this->assertStringContainsString('[required on submit]', $text);
        $this->assertStringContainsString('Planned Activities', $text);
        $this->assertStringContainsString('Key Meetings / Engagements', $text);
        $this->assertStringContainsString('Budget', $text);
        $this->assertStringContainsString('Office', $text);
        $this->assertStringContainsString('Assigned Staff', $text);
    }

    public function test_format_prompt_context_includes_weekly_report_sections_and_progress_options(): void
    {
        $text = app(WeeklyDocumentFormKnowledgeService::class)->formatPromptContext();

        $this->assertStringContainsString('WEEKLY REPORT:', $text);
        $this->assertStringContainsString('Summary of the Week', $text);
        $this->assertStringContainsString('Weekly Activity Progress', $text);
        $this->assertStringContainsString('Done (recurring)', $text);
        $this->assertStringContainsString('Carry to next week', $text);
        $this->assertStringContainsString('Key Achievements', $text);
        $this->assertStringContainsString('Challenges & Mitigation', $text);
        $this->assertStringContainsString('Target accomplishment status', $text);
        $this->assertStringContainsString('Review & Submit', $text);
    }

    public function test_question_needs_form_knowledge_detects_field_questions(): void
    {
        $service = app(WeeklyDocumentFormKnowledgeService::class);

        $this->assertTrue($service->questionNeedsFormKnowledge('What fields do I fill in for a weekly plan?'));
        $this->assertTrue($service->questionNeedsFormKnowledge('What are the progress options on the weekly report?'));
        $this->assertFalse($service->questionNeedsFormKnowledge('Have I submitted my weekly report?'));
    }
}
