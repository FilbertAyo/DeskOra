<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;

class DocumentBudgetReviewController extends DocumentController
{
    public function weeklyBudgetReviewPanel(Request $request)
    {
        return parent::weeklyBudgetReviewPanel($request);
    }

    public function exportWeeklyBudgetReviewPdf(Request $request)
    {
        return parent::exportWeeklyBudgetReviewPdf($request);
    }

    public function exportWeeklyBudgetReviewExcel(Request $request)
    {
        return parent::exportWeeklyBudgetReviewExcel($request);
    }

    public function processWeeklyBudgetReview(Request $request, Document $document)
    {
        return parent::processWeeklyBudgetReview($request, $document);
    }

    public function addMidWeekBudgetEntry(Request $request)
    {
        return parent::addMidWeekBudgetEntry($request);
    }
}
