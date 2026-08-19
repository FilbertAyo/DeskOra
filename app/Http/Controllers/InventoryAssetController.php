<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\InventoryAsset;
use App\Models\InventoryAssetAssignment;
use App\Models\InventoryAssetTransfer;
use App\Models\InventoryCategory;
use App\Models\InventoryDonor;
use App\Models\InventoryLocation;
use App\Models\InventoryProject;
use App\Models\InventorySupplier;
use App\Models\User;
use App\Services\Inventory\InventoryAssetCodeGenerator;
use App\Services\Inventory\InventoryQrCodeGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
            ]
        ));
    }

    public function create()
    {
        $this->authorize('create', InventoryAsset::class);

        return view('inventory.assets.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryAsset::class);

        $validated = $this->validatedAsset($request);
        $validated['asset_code'] = $this->assetCodeGenerator->generate();

        InventoryAsset::query()->create($validated);

        return redirect()->route('inventory.assets.index')->with('success', 'Asset registered successfully.');
    }

    public function show(InventoryAsset $asset)
    {
        $this->authorize('view', $asset);

        $asset->load([
            'category', 'location', 'department', 'responsibleUser',
            'donor', 'project', 'supplier', 'receipt.receivedByUser',
            'assignments.assignedToUser', 'assignments.assignedToDepartment', 'assignments.assignedByUser',
            'transfers.fromLocation', 'transfers.toLocation', 'transfers.transferredByUser',
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

        return view('inventory.assets.show', [
            'asset' => $asset,
            'timeline' => $timeline,
            'statusOptions' => InventoryAsset::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
            'sourceTypeOptions' => InventoryAsset::sourceTypeOptions(),
            'qrDataUri' => $this->qrCodeGenerator->pngDataUri($this->qrCodeGenerator->scanUrl($asset)),
            'scanUrl' => $this->qrCodeGenerator->scanUrl($asset),
        ]);
    }

    public function edit(InventoryAsset $asset)
    {
        $this->authorize('update', $asset);

        return view('inventory.assets.edit', array_merge(compact('asset'), $this->formOptions()));
    }

    public function update(Request $request, InventoryAsset $asset)
    {
        $this->authorize('update', $asset);

        $asset->update($this->validatedAsset($request));

        return redirect()->route('inventory.assets.show', $asset)->with('success', 'Asset updated successfully.');
    }

    public function assignForm(InventoryAsset $asset)
    {
        $this->authorize('assign', $asset);

        $asset->load(['location', 'department', 'responsibleUser']);

        return view('inventory.assets.assign', array_merge(compact('asset'), $this->formOptions()));
    }

    public function assign(Request $request, InventoryAsset $asset)
    {
        $this->authorize('assign', $asset);

        $validated = $request->validate([
            'assigned_to_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'assigned_to_department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($validated['assigned_to_user_id']) && empty($validated['assigned_to_department_id'])) {
            return redirect()->back()->withErrors([
                'assigned_to_user_id' => 'Assign to a staff member or department.',
            ])->withInput();
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($asset, $validated, $user) {
            $asset->assignments()
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);

            InventoryAssetAssignment::query()->create([
                'asset_id' => $asset->id,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'assigned_to_department_id' => $validated['assigned_to_department_id'] ?? null,
                'assigned_by_user_id' => $user->id,
                'notes' => $validated['notes'] ?? null,
                'assigned_at' => now(),
            ]);

            $asset->update([
                'responsible_user_id' => $validated['assigned_to_user_id'] ?? null,
                'department_id' => $validated['assigned_to_department_id'] ?? $asset->department_id,
                'status' => InventoryAsset::STATUS_ASSIGNED,
            ]);
        });

        return redirect()->route('inventory.assets.show', $asset)->with('success', 'Asset assigned successfully.');
    }

    public function transferForm(InventoryAsset $asset)
    {
        $this->authorize('transfer', $asset);

        $asset->load(['location']);

        return view('inventory.assets.transfer', array_merge(compact('asset'), $this->formOptions()));
    }

    public function transfer(Request $request, InventoryAsset $asset)
    {
        $this->authorize('transfer', $asset);

        $validated = $request->validate([
            'to_location_id' => ['required', 'string', 'short_id', 'exists:inventory_locations,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

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

        return redirect()->route('inventory.assets.show', $asset)->with('success', 'Asset transferred successfully.');
    }

    public function scan(string $assetCode)
    {
        $asset = InventoryAsset::query()
            ->with(['category', 'location', 'department', 'responsibleUser'])
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

        $assets = $this->filteredAssetsQuery($request)
            ->orderBy('asset_code')
            ->limit(200)
            ->get();

        $labels = $assets->map(fn (InventoryAsset $asset) => [
            'asset' => $asset,
            'qr' => $this->qrCodeGenerator->pngDataUri($this->qrCodeGenerator->scanUrl($asset), 140),
        ]);

        return view('inventory.assets.labels', compact('labels'));
    }

    private function filteredAssetsQuery(Request $request)
    {
        $query = InventoryAsset::query()
            ->with(['category', 'location', 'department', 'responsibleUser']);

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
            $query->where('location_id', $request->input('location_id'));
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

        foreach (['category_id', 'donor_id', 'project_id', 'supplier_id', 'location_id', 'department_id', 'responsible_user_id'] as $field) {
            if (empty($validated[$field])) {
                $validated[$field] = null;
            }
        }

        return $validated;
    }

    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
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
