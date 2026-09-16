<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryCategory;
use App\Models\InventoryDispatch;
use App\Models\InventoryDispatchItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStockItem;
use App\Models\User;
use App\Services\Inventory\InventoryDispatchProcessor;
use App\Services\Inventory\InventoryNumberGenerator;
use App\Support\InventoryHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryDispatchController extends Controller
{
    public function __construct(
        private readonly InventoryNumberGenerator $numberGenerator,
        private readonly InventoryDispatchProcessor $processor,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryDispatch::class);

        $tab = $request->string('tab')->toString() ?: 'all';

        $query = InventoryDispatch::query()
            ->with(['responsibleUsers', 'createdBy'])
            ->withCount([
                'items as asset_count' => fn ($builder) => $builder->where('item_type', InventoryDispatchItem::TYPE_ASSET),
                'items as stock_count' => fn ($builder) => $builder->where('item_type', InventoryDispatchItem::TYPE_STOCK),
            ]);

        if ($tab === 'draft') {
            $query->whereIn('status', [InventoryDispatch::STATUS_DRAFT, InventoryDispatch::STATUS_READY]);
        } elseif ($tab === 'dispatched') {
            $query->where('status', InventoryDispatch::STATUS_DISPATCHED);
        } elseif ($tab === 'returning') {
            $query->whereIn('status', [InventoryDispatch::STATUS_RETURNING, InventoryDispatch::STATUS_RETURNED]);
        } elseif ($tab === 'overdue') {
            $query->whereIn('status', InventoryDispatch::activeMovementStatuses())
                ->whereDate('expected_return_date', '<', now()->toDateString());
        } elseif ($tab === 'closed') {
            $query->where('status', InventoryDispatch::STATUS_CLOSED);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $dispatches = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'all' => InventoryDispatch::query()->count(),
            'draft' => InventoryDispatch::query()
                ->whereIn('status', [InventoryDispatch::STATUS_DRAFT, InventoryDispatch::STATUS_READY])
                ->count(),
            'dispatched' => InventoryDispatch::query()->where('status', InventoryDispatch::STATUS_DISPATCHED)->count(),
            'returning' => InventoryDispatch::query()
                ->whereIn('status', [InventoryDispatch::STATUS_RETURNING, InventoryDispatch::STATUS_RETURNED])
                ->count(),
            'overdue' => InventoryDispatch::overdueCount(),
            'closed' => InventoryDispatch::query()->where('status', InventoryDispatch::STATUS_CLOSED)->count(),
        ];

        return view('inventory.dispatches.index', [
            'dispatches' => $dispatches,
            'tab' => $tab,
            'counts' => $counts,
            'statusOptions' => InventoryDispatch::statusOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', InventoryDispatch::class);

        return view('inventory.dispatches.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryDispatch::class);

        $validated = $this->validatedHeader($request);

        /** @var User $user */
        $user = Auth::user();

        $dispatch = DB::transaction(function () use ($validated, $user) {
            $responsibleIds = $validated['responsible_user_ids'];
            unset($validated['responsible_user_ids']);

            $dispatch = InventoryDispatch::query()->create([
                ...$validated,
                'dispatch_number' => $this->numberGenerator->generate(
                    InventoryDispatch::class,
                    'dispatch_number',
                    'DSP'
                ),
                'status' => InventoryDispatch::STATUS_DRAFT,
                'created_by_user_id' => $user->id,
            ]);

            $dispatch->responsibleUsers()->sync($responsibleIds);

            return $dispatch;
        });

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->with('success', 'Dispatch created. Add assets and stock items next.');
    }

    public function show(Request $request, InventoryDispatch $dispatch)
    {
        $this->authorize('view', $dispatch);

        $dispatch->load([
            'responsibleUsers',
            'createdBy',
            'dispatchedBy',
            'returnStartedBy',
            'returnedBy',
            'closedBy',
            'items.asset.category',
            'items.asset.location.parent',
            'items.asset.responsibleUser',
            'items.stockItem.category',
            'items.stockItem.location.parent',
            'items.originalLocation.parent',
            'items.originalResponsibleUser',
        ]);

        $picker = $request->string('picker')->toString() ?: 'assets';

        return view('inventory.dispatches.show', array_merge(
            $this->formOptions(),
            [
                'dispatch' => $dispatch,
                'statusOptions' => InventoryDispatch::statusOptions(),
                'conditionOptions' => InventoryAsset::conditionOptions(),
                'assetStatusOptions' => InventoryAsset::statusOptions(),
                'returnOutcomeOptions' => InventoryDispatchItem::returnOutcomeOptions(),
                'picker' => $picker,
                'assetFilters' => $request->only(['search', 'category_id', 'location_id', 'responsible_user_id', 'status', 'condition']),
                'stockFilters' => $request->only(['stock_search', 'stock_category_id', 'stock_location_id']),
                'pickerAssets' => $dispatch->canEditItems() && $picker === 'assets'
                    ? $this->filteredDispatchableAssets($request, $dispatch)->paginate(50, ['*'], 'assets_page')->withQueryString()
                    : null,
                'pickerStockItems' => $dispatch->canEditItems() && $picker === 'stock'
                    ? $this->filteredDispatchableStock($request, $dispatch)->paginate(50, ['*'], 'stock_page')->withQueryString()
                    : null,
                'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
            ]
        ));
    }

    public function edit(InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);

        $dispatch->load('responsibleUsers');

        return view('inventory.dispatches.edit', array_merge(
            compact('dispatch'),
            $this->formOptions()
        ));
    }

    public function update(Request $request, InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);

        $validated = $this->validatedHeader($request);

        DB::transaction(function () use ($dispatch, $validated) {
            $responsibleIds = $validated['responsible_user_ids'];
            unset($validated['responsible_user_ids']);

            $dispatch->update($validated);
            $dispatch->responsibleUsers()->sync($responsibleIds);
        });

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->with('success', 'Dispatch details updated.');
    }

    public function destroy(InventoryDispatch $dispatch)
    {
        $this->authorize('delete', $dispatch);

        $number = $dispatch->dispatch_number;
        $dispatch->delete();

        return redirect()
            ->route('inventory.dispatches.index')
            ->with('success', "Draft {$number} deleted.");
    }

    public function addAssets(Request $request, InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);
        $this->assertCanEditItems($dispatch);

        $addAllMatching = $request->boolean('add_all_matching');
        $assetIds = $request->input('asset_ids', []);

        if (! $addAllMatching && ! is_array($assetIds)) {
            $assetIds = [];
        }

        $query = $this->filteredDispatchableAssets($request, $dispatch);

        if ($addAllMatching) {
            $assets = $query->limit(500)->get();
        } else {
            $ids = array_values(array_filter(array_map('strval', is_array($assetIds) ? $assetIds : [])));
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'asset_ids' => 'Select at least one asset to add.',
                ]);
            }
            $assets = $query->whereIn('id', $ids)->get();
        }

        if ($assets->isEmpty()) {
            throw ValidationException::withMessages([
                'asset_ids' => 'No matching assets were available to add.',
            ]);
        }

        $added = 0;
        DB::transaction(function () use ($dispatch, $assets, &$added) {
            $alreadyOnDispatch = $dispatch->items()
                ->where('item_type', InventoryDispatchItem::TYPE_ASSET)
                ->pluck('asset_id')
                ->all();

            foreach ($assets as $asset) {
                if (in_array($asset->id, $alreadyOnDispatch, true)) {
                    continue;
                }

                InventoryDispatchItem::query()->create([
                    'dispatch_id' => $dispatch->id,
                    'item_type' => InventoryDispatchItem::TYPE_ASSET,
                    'asset_id' => $asset->id,
                    'quantity' => 1,
                    'original_status' => $asset->status,
                    'original_location_id' => $asset->location_id,
                    'original_responsible_user_id' => $asset->responsible_user_id,
                    'original_department_id' => $asset->department_id,
                    'original_condition' => $asset->condition,
                    'condition_at_dispatch' => $asset->condition,
                ]);

                $alreadyOnDispatch[] = $asset->id;
                $added++;
            }
        });

        if ($added === 0) {
            throw ValidationException::withMessages([
                'asset_ids' => 'Those assets are already on this dispatch.',
            ]);
        }

        return redirect()
            ->route('inventory.dispatches.show', array_merge(['dispatch' => $dispatch], $request->only(['picker', 'search', 'category_id', 'location_id', 'responsible_user_id', 'status', 'condition'])))
            ->with('success', $added === 1 ? '1 asset added to the dispatch.' : "{$added} assets added to the dispatch.");
    }

    public function addStock(Request $request, InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);
        $this->assertCanEditItems($dispatch);

        $validated = $request->validate([
            'stock_item_id' => ['required', 'string', 'short_id', 'exists:inventory_stock_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $stockItem = InventoryStockItem::query()->findOrFail($validated['stock_item_id']);
        $quantity = (int) $validated['quantity'];

        $existing = $dispatch->items()
            ->where('item_type', InventoryDispatchItem::TYPE_STOCK)
            ->where('stock_item_id', $stockItem->id)
            ->first();

        $nextQuantity = $quantity + (int) ($existing?->quantity ?? 0);
        if ($nextQuantity > $stockItem->quantity_remaining) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stockItem->quantity_remaining} {$stockItem->unit} remaining for {$stockItem->name}.",
            ]);
        }

        if ($existing) {
            $existing->update(['quantity' => $nextQuantity]);
            $message = "Quantity for {$stockItem->name} updated to {$nextQuantity}.";
        } else {
            InventoryDispatchItem::query()->create([
                'dispatch_id' => $dispatch->id,
                'item_type' => InventoryDispatchItem::TYPE_STOCK,
                'stock_item_id' => $stockItem->id,
                'quantity' => $quantity,
            ]);
            $message = "{$stockItem->name} added to the dispatch.";
        }

        return redirect()
            ->route('inventory.dispatches.show', ['dispatch' => $dispatch, 'picker' => 'stock'])
            ->with('success', $message);
    }

    public function removeItem(InventoryDispatch $dispatch, InventoryDispatchItem $item)
    {
        $this->authorize('update', $dispatch);
        $this->assertCanEditItems($dispatch);

        if ((string) $item->dispatch_id !== (string) $dispatch->id) {
            abort(404);
        }

        $item->delete();

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->with('success', 'Item removed from the dispatch.');
    }

    public function markReady(InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);

        if ($dispatch->status !== InventoryDispatch::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only a draft dispatch can be marked ready.',
            ]);
        }

        $this->assertReadyToLeaveDraft($dispatch);

        $dispatch->update(['status' => InventoryDispatch::STATUS_READY]);

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->with('success', 'Dispatch is ready for review. Confirm it when the items leave.');
    }

    public function reopen(InventoryDispatch $dispatch)
    {
        $this->authorize('update', $dispatch);

        if ($dispatch->status !== InventoryDispatch::STATUS_READY) {
            throw ValidationException::withMessages([
                'status' => 'Only a ready dispatch can be returned to draft.',
            ]);
        }

        $dispatch->update(['status' => InventoryDispatch::STATUS_DRAFT]);

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->with('success', 'Dispatch returned to draft. You can add or remove items.');
    }

    public function confirm(InventoryDispatch $dispatch)
    {
        $this->authorize('confirm', $dispatch);

        /** @var User $user */
        $user = Auth::user();
        $this->processor->confirm($dispatch, $user);

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->withGooeyToast('success', 'Dispatch confirmed', [
                'message' => 'Items are now out on this activity. Permanent custodians were not changed.',
                'details' => [
                    ['label' => 'Dispatch', 'value' => (string) $dispatch->dispatch_number],
                    ['label' => 'Destination', 'value' => (string) $dispatch->destination],
                ],
            ]);
    }

    public function startReturn(InventoryDispatch $dispatch)
    {
        $this->authorize('returnItems', $dispatch);

        /** @var User $user */
        $user = Auth::user();
        $this->processor->startReturn($dispatch, $user);

        return redirect()
            ->route('inventory.dispatches.return', $dispatch)
            ->with('success', 'Return started. Mark everything received and good, then record any exceptions.');
    }

    public function returnForm(InventoryDispatch $dispatch)
    {
        $this->authorize('returnItems', $dispatch);

        if ($dispatch->status === InventoryDispatch::STATUS_DISPATCHED) {
            /** @var User $user */
            $user = Auth::user();
            $this->processor->startReturn($dispatch, $user);
            $dispatch->refresh();
        }

        if (! $dispatch->canRecordReturn()) {
            return redirect()->route('inventory.dispatches.show', $dispatch);
        }

        $dispatch->load([
            'responsibleUsers',
            'items.asset.category',
            'items.asset.responsibleUser',
            'items.stockItem',
        ]);

        return view('inventory.dispatches.return', [
            'dispatch' => $dispatch,
            'statusOptions' => InventoryDispatch::statusOptions(),
            'returnOutcomeOptions' => InventoryDispatchItem::returnOutcomeOptions(),
        ]);
    }

    public function saveReturn(Request $request, InventoryDispatch $dispatch)
    {
        $this->authorize('returnItems', $dispatch);

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['array'],
            'items.*.return_outcome' => ['nullable', 'string', Rule::in(array_keys(InventoryDispatchItem::returnOutcomeOptions()))],
            'items.*.return_notes' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity_returned' => ['nullable', 'integer', 'min:0'],
            'items.*.quantity_consumed' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->processor->saveReturnOutcomes($dispatch, $validated['items'] ?? []);

        return redirect()
            ->route('inventory.dispatches.return', $dispatch)
            ->with('success', 'Return outcomes saved.');
    }

    public function markAllGood(InventoryDispatch $dispatch)
    {
        $this->authorize('returnItems', $dispatch);

        $this->processor->markAllReceivedGood($dispatch);

        return redirect()
            ->route('inventory.dispatches.return', $dispatch)
            ->with('success', 'All items marked received and good. Record any exceptions, then complete the return.');
    }

    public function completeReturn(InventoryDispatch $dispatch)
    {
        $this->authorize('returnItems', $dispatch);

        /** @var User $user */
        $user = Auth::user();
        $this->processor->completeReturn($dispatch, $user);

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->withGooeyToast('success', 'Return completed', [
                'details' => [
                    ['label' => 'Dispatch', 'value' => (string) $dispatch->dispatch_number],
                ],
            ]);
    }

    public function close(InventoryDispatch $dispatch)
    {
        $this->authorize('close', $dispatch);

        /** @var User $user */
        $user = Auth::user();
        $this->processor->close($dispatch, $user);

        return redirect()
            ->route('inventory.dispatches.show', $dispatch)
            ->withGooeyToast('success', 'Dispatch closed', [
                'message' => 'Assets are back on their normal assignments. Stock has been updated.',
                'details' => [
                    ['label' => 'Dispatch', 'value' => (string) $dispatch->dispatch_number],
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedHeader(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:1000'],
            'destination' => ['required', 'string', 'max:255'],
            'dispatch_date' => ['required', 'date'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:dispatch_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'responsible_user_ids' => ['required', 'array', 'min:1'],
            'responsible_user_ids.*' => ['required', 'string', 'short_id', 'exists:users,id'],
        ]);

        $validated['responsible_user_ids'] = array_values(array_unique($validated['responsible_user_ids']));

        return $validated;
    }

    private function assertCanEditItems(InventoryDispatch $dispatch): void
    {
        if (! $dispatch->canEditItems()) {
            throw ValidationException::withMessages([
                'status' => 'Items can only be changed while the dispatch is a draft.',
            ]);
        }
    }

    private function assertReadyToLeaveDraft(InventoryDispatch $dispatch): void
    {
        $dispatch->loadCount('items');
        $dispatch->load('responsibleUsers');

        if ((int) $dispatch->items_count === 0) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item before marking this dispatch ready.',
            ]);
        }

        if ($dispatch->responsibleUsers->isEmpty()) {
            throw ValidationException::withMessages([
                'responsible_user_ids' => 'Select at least one temporary responsible person.',
            ]);
        }
    }

    private function filteredDispatchableAssets(Request $request, InventoryDispatch $dispatch)
    {
        $alreadyIds = $dispatch->items()
            ->where('item_type', InventoryDispatchItem::TYPE_ASSET)
            ->pluck('asset_id');

        $busyAssetIds = InventoryDispatchItem::query()
            ->where('item_type', InventoryDispatchItem::TYPE_ASSET)
            ->whereNotNull('asset_id')
            ->whereHas('dispatch', fn ($builder) => $builder->whereIn('status', InventoryDispatch::openStatuses()))
            ->pluck('asset_id');

        $query = InventoryAsset::query()
            ->with(['category', 'location.parent', 'responsibleUser'])
            ->whereIn('status', InventoryAsset::dispatchableStatuses())
            ->whereNotIn('id', $alreadyIds)
            ->whereNotIn('id', $busyAssetIds);

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

        $locationId = InventoryHierarchy::resolveLocationId($request) ?: $request->input('location_id');
        if (filled($locationId)) {
            InventoryHierarchy::applyLocationFilter($query, $locationId);
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

        return $query->orderBy('asset_code');
    }

    private function filteredDispatchableStock(Request $request, InventoryDispatch $dispatch)
    {
        $query = InventoryStockItem::query()
            ->with(['category', 'location.parent'])
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0);

        $search = $request->input('stock_search', $request->input('search'));
        if (filled($search)) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $categoryId = $request->input('stock_category_id', $request->input('category_id'));
        if (filled($categoryId)) {
            $query->where('category_id', $categoryId);
        }

        $locationId = $request->input('stock_location_id')
            ?: InventoryHierarchy::resolveLocationId($request)
            ?: $request->input('location_id');
        if (filled($locationId)) {
            InventoryHierarchy::applyLocationFilter($query, $locationId);
        }

        return $query->orderBy('name');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'categories' => InventoryCategory::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'locations' => InventoryLocation::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'parent_id']),
        ];
    }
}
