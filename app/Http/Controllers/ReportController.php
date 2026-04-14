<?php

namespace App\Http\Controllers;

use App\Exports\PettyExport;
use App\Exports\UserExport;
use App\Models\Petty;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function usersReport(Request $request)
    {
        $query = User::where('status', 'active');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $query->whereBetween('created_at', [$from, $to]);
        }
        $users = $query->latest()->get();

        return view('reports.users.list', compact('users'));
    }

    public function downloadUsers(Request $request, $type)
    {
        $query = User::where('status', 'active');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $query->whereBetween('created_at', [$from, $to]);
        }

        $users = $query->latest()->get();

        if ($type === 'pdf') {
            $pdf = Pdf::loadView('reports.users.pdf', compact('users'));

            return $pdf->download('USERS_LIST.pdf');
        }

        if (in_array($type, ['excel', 'csv'])) {
            return Excel::download(new UserExport($request->from, $request->to), 'USERS_LIST.'.($type === 'csv' ? 'csv' : 'xlsx'));
        }

        return back();
    }

    public function pettyReport(Request $request)
    {
        $query = Petty::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            // Use different date column depending on status
            if ($request->status === 'paid') {
                $query->whereBetween('paid_date', [$from, $to]);
            } else {
                $query->whereBetween('created_at', [$from, $to]);
            }
        }

        $petties = $query->get();

        return view('reports.petty.list', compact('petties'));
    }

    public function downloadPetty(Request $request, $type)
    {
        $query = Petty::query();

        // Check if both date fields are filled
        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            // Check status and apply appropriate date column
            if ($request->filled('status') && $request->status === 'paid') {
                $query->whereBetween('paid_date', [$from, $to]);
            } else {
                $query->whereBetween('created_at', [$from, $to]);
            }
        }

        // Always filter by status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $petties = $query->get();

        // Export to PDF
        if ($type === 'pdf') {
            $pdf = Pdf::loadView('reports.petty.pdf', compact('petties'));

            return $pdf->download('PETTY CASH.pdf');
        }

        // Export to Excel
        if ($type === 'excel') {
            return Excel::download(new PettyExport($request->from, $request->to, $request->status), 'petty_cash.xlsx');
        }

        return back();
    }

    public function transactionReport(Request $request)
    {
        $query = Petty::query();

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $query->whereBetween('paid_date', [$from, $to]);
        }
        $query->where('status', 'paid');

        $transactions = $query->get();

        return view('reports.transactions.list', compact('transactions'));
    }

    public function downloadTransaction(Request $request, $type)
    {

        $query = Petty::query();

        // Filter by date range
        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            $query->whereBetween('paid_date', [$from, $to]);
        }
        $query->where('status', 'paid');

        $transactions = $query->get();

        // Export to PDF
        if ($type === 'pdf') {
            $pdf = Pdf::loadView('reports.transactions.pdf', compact('transactions'));

            return $pdf->download('TRANSACTIONS.pdf');
        }

        return back();
    }
}
