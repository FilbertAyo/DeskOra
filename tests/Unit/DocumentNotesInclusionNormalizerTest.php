<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentContentAssembler;
use Tests\TestCase;

class DocumentNotesInclusionNormalizerTest extends TestCase
{
    public function test_sanitize_notes_inclusion_decodes_url_encoded_text(): void
    {
        $assembler = app(DocumentContentAssembler::class);

        $raw = 'include:%20meeting%20with%20Regional%20Education%20Officer,%20Project%20Inspire%20engagement';
        $normalized = $assembler->sanitizeNotesInclusion($raw);

        $this->assertSame(
            'meeting with Regional Education Officer, Project Inspire engagement',
            $normalized
        );
    }

    public function test_sanitize_notes_inclusion_preserves_plain_text(): void
    {
        $assembler = app(DocumentContentAssembler::class);

        $this->assertSame(
            'Discuss outreach with partners',
            $assembler->sanitizeNotesInclusion('Discuss outreach with partners')
        );
    }

    public function test_normalize_weekly_plan_content_sanitizes_planned_activity_notes(): void
    {
        $assembler = app(DocumentContentAssembler::class);

        $content = $assembler->normalizeWeeklyPlanContent([
            'planned_activities' => [
                [
                    'day' => 'Monday',
                    'activity' => 'Stakeholder meetings',
                    'notes_inclusion' => 'include:%20follow-up%20calls',
                ],
            ],
        ]);

        $this->assertSame('follow-up calls', $content['planned_activities'][0]['notes_inclusion']);
    }
}
