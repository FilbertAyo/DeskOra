<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryMaintenanceRecord;
use App\Services\Inventory\InventoryNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryMaintenanceController extends Controller
{
    public function __construct(
        private readonly InventoryNumberGenerator $numberGenerator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryMaintenanceRecord::class);

        $tab = $request->string('tab')->toString() ?: 'open';

        $query = InventoryMaintenanceRecord::query()
            ->with(['asset.location', 'reportedBy', 'completedBy']);

        if ($tab === 'open') {
            $query->where('status', InventoryMaintenanceRecord::STATUS_REPORTED);
        } elseif ($tab === 'completed') {
            $query->where('status', InventoryMaintenanceRecord::STATUS_COMPLETED);
        }

        $records = $query->orderByDesc('reported_at')->paginate(20)->withQueryString();

        $counts = [
            'all' => InventoryMaintenanceRecord::query()->count(),
            'open' => InventoryMaintenanceRecord::openCount(),
            'completed' => InventoryMaintenanceRecord::query()
                ->where('status', InventoryMaintenanceRecord::STATUS_COMPLETED)
                ->count(),
        ];

        return view('inventory.maintenance.index', [
            'records' => $records,
            'tab' => $tab,
            'counts' => $counts,
            'statusOptions' => InventoryMaintenanceRecord::statusOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', InventoryMaintenanceRecord::class);

        $asset = null;
        if ($request->filled('asset_id')) {
            $asset = InventoryAsset::query()->findOrFail($request->input('asset_id'));
            $this->authorize('reportProblem', $asset);
        }

        return view('inventory.maintenance.create', [
            'asset' => $asset,
            'assets' => InventoryAsset::query()
                ->whereNotIn('status', [InventoryAsset::STATUS_DISPOSED, InventoryAsset::STATUS_UNDER_REPAIR, InventoryAsset::STATUS_DISPATCHED])
                ->orderBy('name')
                ->get(['id', 'asset_code', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryMaintenanceRecord::class);

        $validated = $request->validate([
            'asset_id' => ['required', 'string', 'short_id', 'exists:inventory_assets,id'],
            'problem_description' => ['required', 'string', 'max:2000'],
        ]);

        $asset = InventoryAsset::query()->findOrFail($validated['asset_id']);
        $this->authorize('reportProblem', $asset);

        if ($asset->isDisposed() || $asset->status === InventoryAsset::STATUS_BORROWED || $asset->isDispatched()) {
            throw ValidationException::withMessages([
                'asset_id' => 'This asset cannot be sent to repair in its current status.',
            ]);
        }

        if ($asset->hasOpenMaintenance()) {
            throw ValidationException::withMessages([
                'asset_id' => 'This asset already has an open maintenance record.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $record = DB::transaction(function () use ($asset, $validated, $user) {
            $record = InventoryMaintenanceRecord::query()->create([
                'record_number' => $this->numberGenerator->generate(
                    InventoryMaintenanceRecord::class,
                    'record_number',
                    'MNT'
                ),
                'asset_id' => $asset->id,
                'status' => InventoryMaintenanceRecord::STATUS_REPORTED,
                'problem_description' => $validated['problem_description'],
                'reported_by_user_id' => $user->id,
                'reported_at' => now(),
                'previous_status' => $asset->status,
                'previous_condition' => $asset->condition,
            ]);

            $asset->update([
                'status' => InventoryAsset::STATUS_UNDER_REPAIR,
                'condition' => $asset->condition === InventoryAsset::CONDITION_DAMAGED
                    ? InventoryAsset::CONDITION_DAMAGED
                    : InventoryAsset::CONDITION_NEEDS_REPAIR,
            ]);

            return $record;
        });

        return redirect()
            ->route('inventory.maintenance.show', $record)
            ->with('success', 'Problem reported. Asset status is now Under Repair.');
    }

    public function show(InventoryMaintenanceRecord $maintenance)
    {
        $this->authorize('view', $maintenance);

        $maintenance->load(['asset.location', 'reportedBy', 'completedBy']);

        return view('inventory.maintenance.show', [
            'record' => $maintenance,
            'statusOptions' => InventoryMaintenanceRecord::statusOptions(),
            'conditionOptions' => InventoryAsset::conditionOptions(),
        ]);
    }

    public function completeForm(InventoryMaintenanceRecord $maintenance)
    {
        $this->authorize('complete', $maintenance);

        $maintenance->load(['asset']);

        return view('inventory.maintenance.complete', [
            'record' => $maintenance,
            'conditionOptions' => InventoryAsset::conditionOptions(),
        ]);
    }

    public function complete(Request $request, InventoryMaintenanceRecord $maintenance)
    {
        $this->authorize('complete', $maintenance);

        $validated = $request->validate([
            'repair_provider' => ['required', 'string', 'max:255'],
            'repaired_on' => ['required', 'date'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
            'condition_after' => ['required', Rule::in(array_keys(InventoryAsset::conditionOptions()))],
            'repair_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($maintenance, $validated, $user) {
            $asset = $maintenance->asset()->lockForUpdate()->firstOrFail();

            $restoredStatus = $maintenance->previous_status === InventoryAsset::STATUS_ASSIGNED
                || $asset->responsible_user_id
                ? InventoryAsset::STATUS_ASSIGNED
                : InventoryAsset::STATUS_AVAILABLE;

            if (in_array($restoredStatus, [InventoryAsset::STATUS_BORROWED, InventoryAsset::STATUS_DISPATCHED, InventoryAsset::STATUS_UNDER_REPAIR, InventoryAsset::STATUS_DISPOSED], true)) {
                $restoredStatus = InventoryAsset::STATUS_AVAILABLE;
            }

            $maintenance->update([
                'status' => InventoryMaintenanceRecord::STATUS_COMPLETED,
                'repair_provider' => $validated['repair_provider'],
                'repaired_on' => $validated['repaired_on'],
                'repair_cost' => $validated['repair_cost'] ?? null,
                'condition_after' => $validated['condition_after'],
                'repair_notes' => $validated['repair_notes'] ?? null,
                'completed_by_user_id' => $user->id,
                'completed_at' => now(),
            ]);

            $asset->update([
                'status' => $restoredStatus,
                'condition' => $validated['condition_after'],
            ]);
        });

        return redirect()
            ->route('inventory.maintenance.show', $maintenance)
            ->with('success', 'Repair recorded. Asset is back in service.');
    }
}
