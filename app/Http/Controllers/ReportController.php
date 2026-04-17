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
        $search = trim((string) request('search', ''));

        $organizationReports = OrganizationReport::query()
            ->with('uploader:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('stored_name', 'like', "%{$search}%")
                        ->orWhereHas('uploader', function ($uploaderQuery) use ($search) {
                            $uploaderQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('reports.index', compact('organizationReports', 'search'));
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

    public function previewOrganizationDocument(OrganizationReport $organizationReport)
    {
        if (! Storage::disk('public')->exists($organizationReport->path)) {
            return $this->previewPlaceholderResponse('Missing file');
        }

        $filePath = Storage::disk('public')->path($organizationReport->path);
        $extension = strtolower(pathinfo($organizationReport->stored_name, PATHINFO_EXTENSION));

        $imagickClass = 'Imagick';

        if ($extension === 'pdf' && class_exists($imagickClass)) {
            try {
                $imagick = new $imagickClass();
                $imagick->setResolution(150, 150);
                $imagick->readImage($filePath.'[0]');
                $imagick->setImageFormat('png');
                $imagick->thumbnailImage(600, 0, true);
                $content = $imagick->getImageBlob();
                $imagick->clear();
                $imagick->destroy();

                return response($content, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=600',
                ]);
            } catch (\Throwable $exception) {
                return $this->previewPlaceholderResponse(strtoupper($extension).' preview unavailable');
            }
        }

        return $this->previewPlaceholderResponse(strtoupper($extension).' preview unavailable');
    }

    private function previewPlaceholderResponse(string $label)
    {
        $safeLabel = e($label);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="420" viewBox="0 0 600 420">
    <rect width="600" height="420" fill="#f8f9fa"/>
    <rect x="35" y="35" width="530" height="350" rx="12" fill="#ffffff" stroke="#dee2e6" stroke-width="2"/>
    <text x="300" y="190" font-size="44" font-family="Arial, sans-serif" text-anchor="middle" fill="#495057">Document</text>
    <text x="300" y="235" font-size="20" font-family="Arial, sans-serif" text-anchor="middle" fill="#6c757d">{$safeLabel}</text>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
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
