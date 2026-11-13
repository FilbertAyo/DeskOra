<?php

namespace App\Http\Controllers;

use App\Models\OrganizationReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
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

        $organizationReports->through(fn (OrganizationReport $report) => [
            'id' => $report->id,
            'name' => $report->name,
            'stored_name' => $report->stored_name,
            'mime_type' => $report->mime_type,
            'size' => $report->size,
            'uploader' => $report->uploader?->name,
            'created_at' => optional($report->created_at)->toIso8601String(),
            'view_url' => route('reports.organization-documents.view', $report),
            'download_url' => route('reports.organization-documents.download', $report),
            'update_url' => route('reports.organization-documents.update', $report),
            'destroy_url' => route('reports.organization-documents.destroy', $report),
        ]);

        return Inertia::render('Reports/Index', [
            'organizationReports' => $organizationReports,
            'search' => $search,
            'canUpload' => $request->user()->can('upload organization reports'),
            'storeUrl' => route('reports.organization-documents.store'),
        ]);
    }

    public function storeOrganizationDocument(Request $request)
    {
        abort_unless($request->user()->can('upload organization reports'), 403);

        $validated = $request->validateWithBag('uploadOrganizationDocument', [
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

    public function updateOrganizationDocument(Request $request, OrganizationReport $organizationReport)
    {
        abort_unless($request->user()->can('upload organization reports'), 403);

        $validated = $request->validateWithBag('editOrganizationDocument', [
            'name' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20480'],
        ]);

        $updateData = [
            'name' => $validated['name'],
        ];

        if (! empty($validated['file'])) {
            $file = $validated['file'];
            $newPath = $file->store('organization-reports', 'public');

            if (Storage::disk('public')->exists($organizationReport->path)) {
                Storage::disk('public')->delete($organizationReport->path);
            }

            $updateData['stored_name'] = $file->getClientOriginalName();
            $updateData['path'] = $newPath;
            $updateData['mime_type'] = $file->getMimeType();
            $updateData['size'] = $file->getSize();
            $updateData['uploaded_by'] = $request->user()->id;
        }

        $organizationReport->update($updateData);

        return back()->with('success', 'Document updated successfully.');
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
                $imagick = new $imagickClass;
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
}
