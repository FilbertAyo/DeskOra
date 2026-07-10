<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use RuntimeException;

class DocumentAiSummaryBuilder
{
    /** @var array<string, string> */
    private array $userNameCache = [];

    public function buildCombined(?Document $plan, ?Document $report): string
    {
        if (! $plan && ! $report) {
            throw new RuntimeException('A weekly plan or report is required for AI summary.');
        }

        $sections = [];

        if ($plan) {
            $plan->loadMissing(['user', 'period', 'sharedUsers:id,name']);
            $sections[] = $this->buildWeeklyPlan($plan);
        } else {
            $sections[] = "=== WEEKLY PLAN ===\nWeekly plan: not submitted or not available.";
        }

        if ($report) {
            $report->loadMissing(['user', 'period']);
            $sections[] = $this->buildWeeklyReport($report, $plan);
        } else {
            $sections[] = "=== WEEKLY REPORT ===\nWeekly report: not submitted or not available.";
        }

        return $this->finalize(explode("\n", implode("\n\n", $sections)));
    }

    private function buildWeeklyPlan(Document $document): string
    {
        $content = is_array($document->content) ? $document->content : [];
        $lines = ['=== WEEKLY PLAN ==='];
        $lines = array_merge($lines, $this->headerLines($document, 'Weekly Plan'));

        $collaborators = $document->sharedUsers
            ?->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all() ?? [];
        if ($collaborators !== []) {
            $lines[] = 'Collaboration staff: '.implode(', ', $collaborators);
        }

        $mainTarget = trim((string) $document->main_target);
        if ($mainTarget !== '') {
            $lines[] = 'Main objective: '.$mainTarget;
        }

        $reflection = trim((string) ($content['objective']['reflection_progress'] ?? ''));
        if ($reflection !== '') {
            $lines[] = 'Reflection / progress: '.$reflection;
        }

        $activities = is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [];
        if ($activities !== []) {
            $lines[] = 'Planned activities:';
            foreach ($activities as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $activity = trim((string) ($row['activity'] ?? ''));
                if ($activity === '') {
                    continue;
                }
                $day = trim((string) ($row['day'] ?? ''));
                $location = trim((string) ($row['location'] ?? ''));
                $notes = trim((string) ($row['notes_inclusion'] ?? ''));
                $team = $this->resolveUserNames($row['team_member_ids'] ?? []);
                $carried = filled($row['carry_over_source_weekly_report_id'] ?? null);
                $label = ($index + 1).'. '.($day !== '' ? $day.' - ' : '').$activity;
                if ($carried) {
                    $label .= ' [Carried from previous report]';
                }
                if ($location !== '') {
                    $label .= ' (Location: '.$location.')';
                }
                if ($team !== []) {
                    $label .= ' (Team: '.implode(', ', $team).')';
                }
                if ($notes !== '') {
                    $label .= ' [Notes: '.$notes.']';
                }
                $lines[] = $label;
            }
        }

        $this->appendMeetings($lines, $content['key_meetings'] ?? [], includeStatus: false);
        $this->appendBudget($lines, $content['budget'] ?? [], $document->budget_review_status_label ?? null);

        return implode("\n", $lines);
    }

    private function buildWeeklyReport(Document $document, ?Document $relatedPlan = null): string
    {
        $content = is_array($document->content) ? $document->content : [];
        $lines = ['=== WEEKLY REPORT ==='];
        $lines = array_merge($lines, $this->headerLines($document, 'Weekly Report'));

        $status = trim((string) ($content['target_accomplished_status'] ?? $document->target_accomplished_status ?? ''));
        if ($status !== '') {
            $lines[] = 'Main target status: '.str_replace('_', ' ', $status);
        }

        $explanation = trim((string) ($content['target_explanation'] ?? $document->target_explanation ?? ''));
        if ($explanation !== '') {
            $lines[] = 'Target explanation: '.$explanation;
        }

        $summary = trim((string) ($content['summary'] ?? ''));
        if ($summary !== '') {
            $lines[] = 'Week summary: '.$summary;
        }

        $activities = is_array($content['activity_progress'] ?? null) ? $content['activity_progress'] : [];
        if ($activities !== []) {
            $lines[] = 'Activity progress:';
            foreach ($activities as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $offPlan = trim((string) ($row['off_plan_activity'] ?? ''));
                $activityOutput = trim((string) ($row['activity_output'] ?? ''));
                $isOffPlan = $offPlan !== '' || $activityOutput === '__off_plan__';

                if ($isOffPlan) {
                    $activity = $offPlan !== '' ? $offPlan : 'Off-plan activity (not described)';
                    $activity = 'Off-plan: '.$activity;
                } else {
                    $activity = $activityOutput;
                }

                if ($activity === '') {
                    continue;
                }

                $progress = trim((string) ($row['progress'] ?? ''));
                $remarks = trim((string) ($row['remarks_challenges'] ?? ''));
                $nextStep = trim((string) ($row['next_step'] ?? ''));
                $label = ($index + 1).'. '.$activity;
                if ($progress !== '') {
                    $label .= ' | Progress: '.$progress;
                }
                if ($remarks !== '') {
                    $label .= ' | Remarks: '.$remarks;
                }
                if ($nextStep !== '') {
                    $label .= ' | Next step: '.$nextStep;
                }
                $lines[] = $label;
            }
        }

        $achievements = is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [];
        if ($achievements !== []) {
            $lines[] = 'Key achievements:';
            foreach ($achievements as $achievement) {
                $text = trim((string) $achievement);
                if ($text !== '') {
                    $lines[] = '- '.$text;
                }
            }
        }

        $challenges = is_array($content['challenges_mitigation'] ?? null) ? $content['challenges_mitigation'] : [];
        if ($challenges !== []) {
            $lines[] = 'Challenges and mitigation:';
            foreach ($challenges as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $challenge = trim((string) ($row['challenge'] ?? ''));
                $action = trim((string) ($row['action_taken_planned'] ?? ''));
                if ($challenge === '' && $action === '') {
                    continue;
                }
                $lines[] = '- Challenge: '.$challenge.($action !== '' ? ' | Action: '.$action : '');
            }
        }

        $this->appendMeetings($lines, $content['key_meetings'] ?? [], includeStatus: true);

        $planForBudget = $relatedPlan;
        if (! $planForBudget && $document->period_id) {
            $planForBudget = Document::findWeeklyPlanForPeriodOwner(
                (string) $document->period_id,
                (string) $document->user_id
            );
        }

        if ($planForBudget) {
            $planContent = is_array($planForBudget->content) ? $planForBudget->content : [];
            $this->appendBudget(
                $lines,
                $planContent['budget'] ?? [],
                $planForBudget->budget_review_status_label ?? null
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function headerLines(Document $document, string $label): array
    {
        $lines = [
            'Document type: '.$label,
            'Staff: '.trim((string) ($document->user?->name ?? 'Unknown')),
        ];

        if ($document->period) {
            $lines[] = 'Work period: Week '.$document->period->week_number.' of '.$document->period->year
                .' ('.$document->period->date_range.')';
        }

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     * @param  mixed  $meetings
     */
    private function appendMeetings(array &$lines, mixed $meetings, bool $includeStatus = false): void
    {
        if (! is_array($meetings) || $meetings === []) {
            return;
        }

        $lines[] = 'Key meetings:';
        foreach ($meetings as $row) {
            if (! is_array($row)) {
                continue;
            }
            $meeting = trim((string) ($row['meeting'] ?? ''));
            if ($meeting === '') {
                continue;
            }
            $date = trim((string) ($row['date'] ?? ''));
            $location = trim((string) ($row['location'] ?? ''));
            $notes = trim((string) ($row['reactions_notes'] ?? ''));
            $status = trim((string) ($row['status'] ?? ''));
            $participants = $this->resolveUserNames($row['participant_user_ids'] ?? []);
            $label = '- '.$meeting;
            if ($date !== '') {
                $label .= ' ('.$date.')';
            }
            if ($location !== '') {
                $label .= ' at '.$location;
            }
            if ($participants !== []) {
                $label .= ' | Participants: '.implode(', ', $participants);
            }
            if ($includeStatus && $status !== '') {
                $label .= ' | Status: '.$status;
            }
            if ($notes !== '') {
                $label .= ' — '.$notes;
            }
            $lines[] = $label;
        }
    }

    /**
     * @param  list<string>  $lines
     * @param  mixed  $budget
     */
    private function appendBudget(array &$lines, mixed $budget, ?string $reviewStatus = null): void
    {
        if (! is_array($budget) || $budget === []) {
            return;
        }

        $header = 'Budget items:';
        if (filled($reviewStatus)) {
            $header .= ' (Review: '.$reviewStatus.')';
        }
        $lines[] = $header;

        foreach ($budget as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = trim((string) ($row['item'] ?? ''));
            if ($item === '') {
                continue;
            }
            $description = trim((string) ($row['description_purpose'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? $row['amount_raw'] ?? ''));
            $label = '- '.$item;
            if ($description !== '') {
                $label .= ': '.$description;
            }
            if ($amount !== '') {
                $label .= ' (Amount: '.$amount.')';
            }
            $lines[] = $label;
        }
    }

    /**
     * @param  mixed  $ids
     * @return list<string>
     */
    private function resolveUserNames(mixed $ids): array
    {
        if (! is_array($ids) || $ids === []) {
            return [];
        }

        $names = [];
        $missing = [];

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if (isset($this->userNameCache[$id])) {
                $names[] = $this->userNameCache[$id];
            } else {
                $missing[] = $id;
            }
        }

        if ($missing !== []) {
            $found = User::query()
                ->whereIn('id', $missing)
                ->pluck('name', 'id');

            foreach ($missing as $id) {
                $name = trim((string) ($found[$id] ?? ''));
                if ($name === '') {
                    continue;
                }
                $this->userNameCache[$id] = $name;
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    private function finalize(array|string $lines): string
    {
        $text = trim(is_array($lines) ? implode("\n", $lines) : $lines);

        if ($text === '') {
            throw new RuntimeException('This submission has no content to summarize yet.');
        }

        return mb_substr($text, 0, 12000);
    }
}
