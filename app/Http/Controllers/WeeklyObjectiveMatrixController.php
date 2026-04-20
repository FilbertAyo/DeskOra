<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\WorkPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WeeklyObjectiveMatrixController extends Controller
{
    public function download(Request $request)
    {
        $currentPeriod = WorkPeriod::getCurrent();
        if (! $currentPeriod) {
            return back()->with('error', 'No active work period found for weekly objective matrix.');
        }

        $orientation = strtolower((string) $request->query('orientation', 'portrait'));
        if (! in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }

        $matrixRows = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('state', Document::STATE_SUBMITTED)
            ->where('period_id', $currentPeriod->id)
            ->whereNotNull('main_target')
            ->where('main_target', '!=', '')
            ->with(['user:id,name,department_id', 'user.department:id,name'])
            ->latest('updated_at')
            ->get(['user_id', 'main_target', 'content', 'updated_at'])
            ->groupBy('user_id')
            ->map(fn ($plans) => $plans->first())
            ->values()
            ->sortBy(fn ($plan) => strtolower($plan->user?->name ?? ''))
            ->values();

        $pdf = Pdf::loadView('documents.weekly-objective-matrix-pdf', [
            'currentPeriod' => $currentPeriod,
            'matrixRows' => $matrixRows,
            'orientation' => $orientation,
        ])->setPaper('a4', $orientation);

        $filename = sprintf(
            'weekly_objective_matrix_week_%s_%s.pdf',
            (string) $currentPeriod->week_number,
            $orientation
        );

        return $pdf->download($filename);
    }
}
