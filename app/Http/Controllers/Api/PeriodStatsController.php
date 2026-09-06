<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\WorkPeriod;
use Illuminate\Http\Request;

class PeriodStatsController extends Controller
{
    /**
     * Return current work period info, deadlines, and pending counts for the authenticated user.
     */
    public function current(Request $request)
    {
        $user = $request->user();
        $current = WorkPeriod::getCurrent();
        $next = $current?->getNextPeriod();

        $response = [
            'current_period' => $current ? $this->formatPeriod($current) : null,
            'next_period' => $next ? $this->formatPeriod($next, false) : null,
            'pending' => [
                'weekly_plan' => Document::drafts()->forUser($user->id)->where('type', Document::TYPE_WEEKLY_PLAN)->count(),
                'weekly_report' => Document::drafts()->forUser($user->id)->where('type', Document::TYPE_WEEKLY_REPORT)->count(),
                'monthly_report' => Document::drafts()->forUser($user->id)->where('type', Document::TYPE_MONTHLY_REPORT)->count(),
            ],
            'submitted' => [
                'weekly_plan' => Document::forUser($user->id)->weeklyPlans()->submitted()->count(),
                'weekly_report' => Document::forUser($user->id)->weeklyReports()->submitted()->count(),
                'monthly_report' => Document::forUser($user->id)->monthlyReports()->submitted()->count(),
            ],
        ];

        return response()->json($response);
    }

    /**
     * Return recent timeline entries (last 6 weeks) for the authenticated user.
     */
    public function timeline(Request $request)
    {
        $user = $request->user();

        $timeline = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(6)
            ->get()
            ->map(function (WorkPeriod $period) use ($user) {
                $plan = Document::where('user_id', $user->id)
                    ->where('period_id', $period->id)
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->first();

                $report = Document::where('user_id', $user->id)
                    ->where('period_id', $period->id)
                    ->where('type', Document::TYPE_WEEKLY_REPORT)
                    ->first();

                return [
                    'period' => $this->formatPeriod($period),
                    'plan' => $plan ? $this->formatDocument($plan) : null,
                    'report' => $report ? $this->formatDocument($report) : null,
                ];
            })
            ->values();

        return response()->json(['data' => $timeline]);
    }

    /**
     * Overview stats for dashboards (counts, deadlines, totals).
     */
    public function overview(Request $request)
    {
        $user = $request->user();
        $current = WorkPeriod::getCurrent();

        $totalDocuments = Document::forUser($user->id)->count();
        $submitted = Document::forUser($user->id)->submitted()->count();
        $drafts = Document::forUser($user->id)->drafts()->count();

        return response()->json([
            'documents' => [
                'total' => $totalDocuments,
                'submitted' => $submitted,
                'drafts' => $drafts,
            ],
            'deadlines' => $current ? [
                'plan' => [
                    'date' => $current->plan_deadline,
                    'status' => $current->getPlanDeadlineStatus(),
                    'badge' => $current->getPlanDeadlineBadgeClass(),
                ],
                'report' => [
                    'date' => $current->report_deadline,
                    'status' => $current->getReportDeadlineStatus(),
                    'badge' => $current->getReportDeadlineBadgeClass(),
                ],
            ] : null,
        ]);
    }

    private function formatPeriod(WorkPeriod $period, bool $includeStatus = true): array
    {
        return [
            'id' => $period->id,
            'week_number' => $period->week_number,
            'year' => $period->year,
            'date_range' => $period->date_range,
            'plan_deadline' => [
                'date' => $period->plan_deadline,
                'status' => $period->getPlanDeadlineStatus(),
                'badge' => $period->getPlanDeadlineBadgeClass(),
            ],
            'report_deadline' => [
                'date' => $period->report_deadline,
                'status' => $period->getReportDeadlineStatus(),
                'badge' => $period->getReportDeadlineBadgeClass(),
            ],
            'status' => $includeStatus ? $period->status : null,
        ];
    }

    private function formatDocument(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->display_title,
            'type' => $document->type,
            'state' => $document->state,
            'submitted_at' => $document->state === Document::STATE_SUBMITTED ? $document->updated_at : null,
        ];
    }
}
