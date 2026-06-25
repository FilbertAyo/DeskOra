<?php

namespace Tests\Unit;

use App\Models\Document;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentWeeklyReportActivitySortTest extends TestCase
{
    #[Test]
    public function test_sorts_activity_progress_by_weekday_then_off_plan(): void
    {
        $rows = [
            ['activity_output' => '__off_plan__', 'off_plan_activity' => 'Emergency visit'],
            ['activity_output' => 'Wednesday - Site visit', 'progress' => 'Done'],
            ['activity_output' => 'Monday - Team sync', 'progress' => 'Done'],
            ['activity_output' => 'Friday - Reporting', 'progress' => 'Done'],
            ['activity_output' => 'Tuesday - Training', 'progress' => 'Done'],
        ];

        $sorted = Document::sortWeeklyReportActivityProgress($rows);

        $this->assertSame('Monday - Team sync', $sorted[0]['activity_output']);
        $this->assertSame('Tuesday - Training', $sorted[1]['activity_output']);
        $this->assertSame('Wednesday - Site visit', $sorted[2]['activity_output']);
        $this->assertSame('Friday - Reporting', $sorted[3]['activity_output']);
        $this->assertSame('__off_plan__', $sorted[4]['activity_output']);
    }
}
