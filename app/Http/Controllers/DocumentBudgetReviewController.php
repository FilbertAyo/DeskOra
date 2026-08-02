<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDocumentBudgetReview;

class DocumentBudgetReviewController extends DocumentController
{
    use HandlesDocumentBudgetReview;
}
