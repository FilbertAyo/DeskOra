<?php

namespace App\Http\Controllers;

use App\Exports\InventoryAssetsExport;
use App\Models\Department;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetAssignment;
use App\Models\InventoryAssetTransfer;
use App\Models\InventoryCategory;
use App\Models\InventoryDonor;
use App\Models\InventoryLocation;
use App\Models\InventoryProject;
use App\Models\InventorySupplier;
use App\Models\InventoryVerificationResult;
use App\Models\User;
use App\Services\Inventory\InventoryAssetCodeGenerator;
use App\Services\Inventory\InventoryQrCodeGenerator;
use App\Support\InventoryHierarchy;
use App\Support\InventoryReturnUrl;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class InventoryAssetController extends Controller
{
    public function __construct(
        private readonly InventoryAssetCodeGenerator $assetCodeGenerator,
        private readonly InventoryQrCodeGenerator $qrCodeGenerator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryAsset::class);

        $query = $this->filteredAssetsQuery($request);

        $assets = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('inventory.assets.index', array_merge(
            compact('assets'),
            $this->formOptions(),
            [
                'filters' => $request->only(['search', 'category_id', 'location_id', 'department_id', 'responsible_user_id', 'status', 'condition']),
                'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
            ]
        ));
    }

    public function create()
    {
        $this->authorize('create', InventoryAsset::class);

        return view('inventory.assets.create', array_merge(
            $this->formOptions(),
            ['inventoryPickerData' => InventoryHierarchy::pickerPayload()]
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryAsset::class);

        $validated = $this->validatedAsset($request);
        $validated['asset_code'] = $this->assetCodeGenerator->generate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($validated, $user) {
            $asset = InventoryAsset::query()->create($validated);

            if ($asset->department_id || $asset->responsible_user_id) {
                $this->recordAssignment($asset, $user, $asset->responsible_user_id, $asset->department_id);
            }
        });

        return redirect()->route('inventory.assets.index')->with('success', 'Asset registered successfully.');
    }

    public function show(InventoryAsset $asset)
    {
        $this->authorize('view', $asset);

        $asset->load([
            'category', 'location.parent', 'department.parent', 'responsibleUser',
            'donor', 'project', 'supplier', 'receipt.receivedByUser',
            'assignments.assignedToUser', 'assignments.assignedToDepartment.parent', 'assignments.assignedByUser',
            'transfers.fromLocation.parent', 'transfers.toLocation.parent', 'transfers.transferredByUser',
            'borrowRequests.borrower', 'borrowRequests.requestedBy',
            'maintenanceRecords.reportedBy', 'maintenanceRecords.completedBy',
            'disposalRequests.requestedBy', 'disposalRequests.reviewedBy',
        ]);

        $timeline = collect();

        if ($asset->receipt) {
            $timeline->push([
                'type' => 'receipt',
                'at' => $asset->receipt->received_at?->startOfDay() ?? $asset->receipt->created_at,
                'record' => $asset->receipt,
            ]);
        }

        $timeline = $timeline
            ->merge($asset->assignments->map(fn ($row) => [
                'type' => 'assignment',
                'at' => $row->assigned_at,
                'record' => $row,
            ]))
            ->merge($asset->transfers->map(fn ($row) => [
                'type' => 'transfer',
                'at' => $row->transferred_at,
                'record' => $row,
            ]));

        foreach ($asset->borrowRequests as $request) {
            $timeline->push([
                'type' => 'borrow',
                'at' => $request->created_at,
                'record' => $request,
            ]);

            if ($request->issued_at) {
                $timeline->push([
                    'type' => 'borrow_issue',
                    'at' => $request->issued_at,
                    'record' => $request,
                ]);
            }

            if ($request->returned_at) {
                $timeline->push([
                    'type' => 'borrow_return',
                    'at' => $request->returned_at,
                    'record' => $request,
                ]);
            }
        }

        foreach ($asset->maintenanceRecords as $record) {
            $timeline->push([
                'type' => 'maintenance_report',
                'at' => $record->reported_at,
                'record' => $record,
            ]);

            if ($record->completed_at) {
                $timeline->push([
                    'type' => 'maintenance_complete',
                    'at' => $record->completed_at,
                    'record' => $record,
                ]);
            }
        }

        foreach ($asset->disposalRequests as $disposal) {
            $timeline->push([
                'type' => 'disposal_request',
                'at' => $disposal->created_at,
                'record' => $disposal,
            ]);

            if ($disposal->disposed_at) {
                $timeline->push([
                    'type' => 'disposal_approved',
                    'at' => $disposal->disposed_at,
                    'record' => $disposal,
                ]);
            }
        }

        $timeline = $timeline
            ->filter(fn ($entry) => $entry['at'] !== null)
            ->sortByDesc('at')
            ->values();

        return view('inventory.assets.show', array_merge($this->formOptions(), [
            'asset' => $asset,
            'timeline' => $timeline,
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
            'sourceTypeOptions' => InventoryAsset::sourceTypeOptions(),
            'qrDataUri' => $this->qrCodeGenerator->pngDataUri($this->qrCodeGenerator->scanUrl($asset)),
            'scanUrl' => $this->qrCodeGenerator->scanUrl($asset),
            'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
            'assignPrefillDepartmentId' => $asset->department_id,
            'assignPrefillUserId' => $asset->responsible_user_id,
        ]));
    }

    public function edit(InventoryAsset $asset)
    {
        $this->authorize('update', $asset);

        return view('inventory.assets.edit', array_merge(
            compact('asset'),
            $this->formOptions(),
            ['inventoryPickerData' => InventoryHierarchy::pickerPayload()]
        ));
    }

    public function update(Request $request, InventoryAsset $asset)
    {
        $this->authorize('update', $asset);

        $previousDepartmentId = $asset->department_id;
        $previousUserId = $asset->responsible_user_id;
        $validated = $this->validatedAsset($request);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($asset, $validated, $user, $previousDepartmentId, $previousUserId) {
            $asset->update($validated);

            $departmentChanged = (string) $previousDepartmentId !== (string) $asset->department_id;
            $userChanged = (string) $previousUserId !== (string) $asset->responsible_user_id;

            if (($departmentChanged || $userChanged) && ($asset->department_id || $asset->responsible_user_id)) {
                $this->recordAssignment($asset, $user, $asset->responsible_user_id, $asset->department_id);
            }
        });

        return redirect()->route('inventory.assets.show', $asset)->with('success', 'Asset updated successfully.');
    }

    public function destroy(Request $request, InventoryAsset $asset)
    {
        $this->authorize('delete', $asset);

        if ($asset->disposalRequests()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'This asset has disposal history and cannot be deleted. Use disposal workflow instead.');
        }

        if (InventoryVerificationResult::query()->where('asset_id', $asset->id)->exists()) {
            return redirect()
                ->back()
                ->with('error', 'This asset appears in a verification session and cannot be deleted.');
        }

        $assetCode = $asset->asset_code;
        $asset->delete();

        return InventoryReturnUrl::redirect($request, route('inventory.assets.index'))
            ->with('success', "Asset {$assetCode} deleted successfully.");
    }

    public function assignForm(InventoryAsset $asset)
    {
        $this->authorize('assign', $asset);

        return redirect()
            ->route('inventory.assets.show', $asset)
            ->with('open_assign_modal', true);
    }

    public function assign(Request $request, InventoryAsset $asset)
    {
        $this->authorize('assign', $asset);

        $ownership = InventoryHierarchy::resolveOwnership($request, 'assigned_to_user_id', 'assigned_to_department_id');

        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'assigned_to_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'parent_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'sub_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignedUserId = $ownership['user_id'];
        $assignedDepartmentId = $ownership['department_id'];

        if (empty($assignedUserId) && empty($assignedDepartmentId)) {
            return redirect()->back()->withErrors([
                'parent_department_id' => 'Assign to a department, sub-department, or staff member.',
            ])->withInput()->with('open_assign_modal', true);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($asset, $assignedUserId, $assignedDepartmentId, $validated, $user) {
            $this->recordAssignment(
                $asset,
                $user,
                $assignedUserId,
                $assignedDepartmentId,
                $validated['notes'] ?? null,
                InventoryAsset::STATUS_ASSIGNED,
            );
        });

        return InventoryReturnUrl::redirect($request, route('inventory.assets.show', $asset))
            ->with('success', 'Asset assigned successfully.')
            ->with('highlight_id', $asset->id);
    }

    public function transferForm(Request $request, InventoryAsset $asset)
    {
        $this->authorize('transfer', $asset);

        $asset->load(['location.parent']);

        $returnUrl = InventoryReturnUrl::sanitize($request->query('return_url'))
            ?: InventoryReturnUrl::sanitize(url()->previous())
            ?: route('inventory.assets.show', $asset);

        return view('inventory.assets.transfer', array_merge(
            compact('asset'),
            $this->formOptions(),
            [
                'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
                'returnUrl' => $returnUrl,
            ]
        ));
    }

    public function transfer(Request $request, InventoryAsset $asset)
    {
        $this->authorize('transfer', $asset);

        $toLocationId = InventoryHierarchy::resolveLocationId($request, 'to_location_id')
            ?: $request->input('to_location_id');

        $validated = $request->validate([
            'to_location_id' => ['required', 'string', 'short_id', 'exists:inventory_locations,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $validated['to_location_id'] = $toLocationId ?: $validated['to_location_id'];

        if ($validated['to_location_id'] === (string) $asset->location_id) {
            return redirect()->back()->withErrors([
                'to_location_id' => 'Choose a different destination location.',
            ])->withInput();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($asset, $validated, $user) {
            InventoryAssetTransfer::query()->create([
                'asset_id' => $asset->id,
                'from_location_id' => $asset->location_id,
                'to_location_id' => $validated['to_location_id'],
                'reason' => $validated['reason'],
                'transferred_by_user_id' => $user->id,
                'transferred_at' => now(),
            ]);

            $asset->update([
                'location_id' => $validated['to_location_id'],
            ]);
        });

        return InventoryReturnUrl::redirect($request, route('inventory.assets.show', $asset))
            ->with('success', 'Asset transferred successfully.')
            ->with('highlight_id', $asset->id);
    }

    public function scan(string $assetCode)
    {
        $asset = InventoryAsset::query()
            ->with(['category', 'location.parent', 'department', 'responsibleUser'])
            ->where('asset_code', $assetCode)
            ->firstOrFail();

        $this->authorize('view', $asset);

        return view('inventory.assets.scan', [
            'asset' => $asset,
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
        ]);
    }

    public function labels(Request $request)
    {
        $this->authorize('viewAny', InventoryAsset::class);

        $locationId = $request->input('location_id');

        if (! filled($locationId)) {
            return redirect()
                ->route('inventory.assets.index')
                ->with('open_qr_modal', true)
                ->with('info', 'Select a room to generate QR labels.');
        }

        $location = InventoryLocation::query()->findOrFail($locationId);

        if (! $location->isRoom()) {
            return redirect()
                ->route('inventory.assets.index')
                ->with('open_qr_modal', true)
                ->with('error', 'Please select a specific room for QR labels.');
        }

        $assets = InventoryAsset::query()
            ->with(['category', 'location.parent'])
            ->where('location_id', $location->id)
            ->orderBy('asset_code')
            ->limit(200)
            ->get();

        $labels = $assets->map(fn (InventoryAsset $asset) => [
            'asset' => $asset,
            'qr' => $this->qrCodeGenerator->pngDataUri($this->qrCodeGenerator->scanUrl($asset), 140),
        ]);

        return view('inventory.assets.labels', compact('labels', 'location'));
    }

    public function export(Request $request, string $format)
    {
        $this->authorize('viewAny', InventoryAsset::class);

        $assets = $this->filteredAssetsQuery($request)
            ->orderBy('asset_code')
            ->get();

        $filename = 'inventory-assets-'.now()->format('Y-m-d');

        if ($request->filled('department_id')) {
            $department = Department::query()->find($request->input('department_id'));
            if ($department) {
                $filename .= '-dept-'.str($department->name)->slug('-');
            }
        }

        if ($request->filled('location_id')) {
            $location = InventoryLocation::query()->find($request->input('location_id'));
            if ($location) {
                $filename .= '-loc-'.str($location->pathLabel())->slug('-');
            }
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('inventory.exports.assets-pdf', [
                'assets' => $assets,
                'statusOptions' => InventoryAsset::statusOptions(),
                'conditionOptions' => InventoryAsset::conditionOptions(),
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(new InventoryAssetsExport($assets), $filename.'.xlsx');
    }

    private function filteredAssetsQuery(Request $request)
    {
        $query = InventoryAsset::query()
            ->with(['category', 'location.parent', 'department', 'responsibleUser']);

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('asset_code', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('location_id')) {
            InventoryHierarchy::applyLocationFilter($query, $request->input('location_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('responsible_user_id')) {
            $query->where('responsible_user_id', $request->input('responsible_user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        return $query;
    }

    private function validatedAsset(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'string', 'short_id', 'exists:inventory_categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'date_received' => ['nullable', 'date'],
            'source_type' => ['required', Rule::in(array_keys(InventoryAsset::sourceTypeOptions()))],
            'donor_id' => ['nullable', 'string', 'short_id', 'exists:inventory_donors,id'],
            'project_id' => ['nullable', 'string', 'short_id', 'exists:inventory_projects,id'],
            'supplier_id' => ['nullable', 'string', 'short_id', 'exists:inventory_suppliers,id'],
            'purchase_value' => ['nullable', 'numeric', 'min:0'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'warranty_info' => ['nullable', 'string', 'max:1000'],
            'location_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
            'department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'responsible_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'condition' => ['required', Rule::in(array_keys(InventoryAsset::conditionOptions()))],
            'status' => ['required', Rule::in(array_keys(InventoryAsset::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ownership = InventoryHierarchy::resolveOwnership($request, 'responsible_user_id', 'department_id');
        $validated['location_id'] = InventoryHierarchy::resolveLocationId($request) ?: ($validated['location_id'] ?? null);
        $validated['department_id'] = $ownership['department_id'];
        $validated['responsible_user_id'] = $ownership['user_id'];

        foreach (['category_id', 'donor_id', 'project_id', 'supplier_id', 'location_id', 'department_id', 'responsible_user_id'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    private function recordAssignment(
        InventoryAsset $asset,
        User $actor,
        ?string $userId,
        ?string $departmentId,
        ?string $notes = null,
        ?string $status = null,
    ): void {
        $asset->assignments()
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        InventoryAssetAssignment::query()->create([
            'asset_id' => $asset->id,
            'assigned_to_user_id' => $userId,
            'assigned_to_department_id' => $departmentId,
            'assigned_by_user_id' => $actor->id,
            'notes' => $notes,
            'assigned_at' => now(),
        ]);

        $updates = [
            'responsible_user_id' => $userId,
            'department_id' => $departmentId ?: $asset->department_id,
        ];

        if ($status) {
            $updates['status'] = $status;
        }

        $asset->update($updates);
    }

    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'donors' => InventoryDonor::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'projects' => InventoryProject::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'suppliers' => InventorySupplier::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name', 'department_id']),
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
            'sourceTypeOptions' => InventoryAsset::sourceTypeOptions(),
        ];
    }
}
