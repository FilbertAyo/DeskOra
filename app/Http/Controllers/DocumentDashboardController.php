<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DocumentDashboardController extends DocumentController
{
    public function reviewerDashboard(Request $request)
    {
        return parent::reviewerDashboard($request);
    }

    public function weeklyPlans(Request $request)
    {
        return parent::weeklyPlans($request);
    }

    public function weeklyReports(Request $request)
    {
        return parent::weeklyReports($request);
    }

    public function monthlyReports(Request $request)
    {
        return parent::monthlyReports($request);
    }

    public function getPlanTarget(Request $request)
    {
        return parent::getPlanTarget($request);
    }
}
