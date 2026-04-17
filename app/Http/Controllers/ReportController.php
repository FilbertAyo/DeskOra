<?php

namespace App\Http\Controllers;

use App\Exports\PettyExport;
use App\Exports\UserExport;
use App\Models\OrganizationReport;
use App\Models\Petty;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index()
    {
        $organizationReports = OrganizationReport::query()
            ->with('uploader:id,name')
            ->latest()
            ->paginate(10);

        return view('reports.index', compact('organizationReports'));
    }

    public function storeOrganizationDocument(Request $request)
    {
        abort_unless($request->user()->can('upload organization reports'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20480'],
        ]);

        $file = $validated['file'];
        $path = $file->store('organization-reports', 'public');

        OrganizationReport::create([
            'name' => $validated['name'],
            'stored_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function showOrganizationDocument(OrganizationReport $organizationReport)
    {
        if (! Storage::disk('public')->exists($organizationReport->path)) {
            return back()->with('error', 'Document file was not found in storage.');
        }

        return response()->file(
            Storage::disk('public')->path($organizationReport->path),
            [
                'Content-Type' => $organizationReport->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$organizationReport->stored_name.'"',
            ]
        );
    }

    public function downloadOrganizationDocument(OrganizationReport $organizationReport)
    {
        if (! Storage::disk('public')->exists($organizationReport->path)) {
            return back()->with('error', 'Document file was not found in storage.');
        }

        return response()->download(
            Storage::disk('public')->path($organizationReport->path),
            $organizationReport->stored_name
        );
    }

    public function destroyOrganizationDocument(Request $request, OrganizationReport $organizationReport)
    {
        abort_unless($request->user()->can('upload organization reports'), 403);

        if (Storage::disk('public')->exists($organizationReport->path)) {
            Storage::disk('public')->delete($organizationReport->path);
        }

        $organizationReport->delete();

        return back()->with('success', 'Document deleted successfully.');
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
