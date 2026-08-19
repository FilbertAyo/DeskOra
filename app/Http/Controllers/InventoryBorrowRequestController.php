<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryStockItem;
use App\Models\User;
use App\Services\Inventory\InventoryBorrowRequestNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryBorrowRequestController extends Controller
{
    public function __construct(
        private readonly InventoryBorrowRequestNumberGenerator $numberGenerator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryBorrowRequest::class);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $tab = $request->string('tab')->toString() ?: 'all';

        $query = InventoryBorrowRequest::query()
            ->with(['asset', 'stockItem', 'borrower', 'requestedBy']);

        if ($tab === 'mine') {
            $query->where(function ($builder) use ($user) {
                $builder->where('requested_by_user_id', $user->id)
                    ->orWhere('borrower_user_id', $user->id);
            });
        } elseif ($tab === 'pending') {
            $query->where('status', InventoryBorrowRequest::STATUS_REQUESTED);
        } elseif ($tab === 'issued') {
            $query->where('status', InventoryBorrowRequest::STATUS_ISSUED);
        } elseif ($tab === 'overdue') {
            $query->where('status', InventoryBorrowRequest::STATUS_ISSUED)
                ->whereDate('expected_return_date', '<', now()->toDateString());
        } elseif ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'all' => InventoryBorrowRequest::query()->count(),
            'mine' => InventoryBorrowRequest::query()
                ->where(function ($builder) use ($user) {
                    $builder->where('requested_by_user_id', $user->id)
                        ->orWhere('borrower_user_id', $user->id);
                })
                ->count(),
            'pending' => InventoryBorrowRequest::pendingReviewCount(),
            'issued' => InventoryBorrowRequest::query()->where('status', InventoryBorrowRequest::STATUS_ISSUED)->count(),
            'overdue' => InventoryBorrowRequest::overdueCount(),
        ];

        return view('inventory.borrowing.index', [
            'requests' => $requests,
            'tab' => $tab,
            'counts' => $counts,
            'statusOptions' => InventoryBorrowRequest::statusOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', InventoryBorrowRequest::class);

        return view('inventory.borrowing.create', array_merge(
            $this->formOptions(),
            [
                'prefillAssetId' => $request->input('asset_id'),
                'prefillStockItemId' => $request->input('stock_item_id'),
            ]
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryBorrowRequest::class);

        $validated = $this->validatedRequest($request);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $borrowRequest = InventoryBorrowRequest::query()->create([
            ...$validated,
            'request_number' => $this->numberGenerator->generate(),
            'status' => InventoryBorrowRequest::STATUS_REQUESTED,
            'requested_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('inventory.borrowing.show', $borrowRequest)
            ->with('success', 'Borrow request submitted.');
    }

    public function show(InventoryBorrowRequest $borrowRequest)
    {
        $this->authorize('view', $borrowRequest);

        $borrowRequest->load([
            'asset', 'stockItem', 'borrower', 'requestedBy',
            'reviewedBy', 'issuedBy', 'returnedBy',
        ]);

        return view('inventory.borrowing.show', [
            'borrowRequest' => $borrowRequest,
            'statusOptions' => InventoryBorrowRequest::statusOptions(),
            'returnConditionOptions' => InventoryBorrowRequest::returnConditionOptions(),
        ]);
    }

    public function approve(Request $request, InventoryBorrowRequest $borrowRequest)
    {
        $this->authorize('approve', $borrowRequest);
        $this->assertStatus($borrowRequest, InventoryBorrowRequest::STATUS_REQUESTED);
        $this->assertNotSelfReview($borrowRequest);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $borrowRequest->update([
            'status' => InventoryBorrowRequest::STATUS_APPROVED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return redirect()->route('inventory.borrowing.show', $borrowRequest)->with('success', 'Request approved.');
    }

    public function reject(Request $request, InventoryBorrowRequest $borrowRequest)
    {
        $this->authorize('reject', $borrowRequest);
        $this->assertStatus($borrowRequest, InventoryBorrowRequest::STATUS_REQUESTED);
        $this->assertNotSelfReview($borrowRequest);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $borrowRequest->update([
            'status' => InventoryBorrowRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return redirect()->route('inventory.borrowing.show', $borrowRequest)->with('success', 'Request rejected.');
    }

    public function issue(InventoryBorrowRequest $borrowRequest)
    {
        $this->authorize('issue', $borrowRequest);
        $this->assertStatus($borrowRequest, InventoryBorrowRequest::STATUS_APPROVED);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($borrowRequest, $user) {
            if ($borrowRequest->item_type === InventoryBorrowRequest::TYPE_ASSET) {
                $asset = InventoryAsset::query()->findOrFail($borrowRequest->asset_id);
                if (! in_array($asset->status, [InventoryAsset::STATUS_AVAILABLE, InventoryAsset::STATUS_ASSIGNED], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'This asset is not currently available to issue.',
                    ]);
                }
                $asset->update(['status' => InventoryAsset::STATUS_BORROWED]);
            } else {
                $stockItem = InventoryStockItem::query()->findOrFail($borrowRequest->stock_item_id);
                if ($borrowRequest->quantity > $stockItem->quantity_remaining) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Not enough remaining stock to issue this request.',
                    ]);
                }
                $stockItem->update([
                    'quantity_issued' => $stockItem->quantity_issued + $borrowRequest->quantity,
                    'quantity_remaining' => $stockItem->quantity_remaining - $borrowRequest->quantity,
                ]);
            }

            $borrowRequest->update([
                'status' => InventoryBorrowRequest::STATUS_ISSUED,
                'issued_by_user_id' => $user->id,
                'issued_at' => now(),
            ]);
        });

        return redirect()->route('inventory.borrowing.show', $borrowRequest)->with('success', 'Item issued to borrower.');
    }

    public function returnItem(Request $request, InventoryBorrowRequest $borrowRequest)
    {
        $this->authorize('returnItem', $borrowRequest);
        $this->assertStatus($borrowRequest, InventoryBorrowRequest::STATUS_ISSUED);

        $validated = $request->validate([
            'return_condition' => ['required', Rule::in(array_keys(InventoryBorrowRequest::returnConditionOptions()))],
            'quantity_returned' => ['nullable', 'integer', 'min:0'],
            'return_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $quantityReturned = (int) ($validated['quantity_returned'] ?? $borrowRequest->quantity);
        if ($borrowRequest->item_type === InventoryBorrowRequest::TYPE_STOCK && $quantityReturned > $borrowRequest->quantity) {
            throw ValidationException::withMessages([
                'quantity_returned' => 'Cannot return more than was issued.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($borrowRequest, $validated, $quantityReturned, $user) {
            if ($borrowRequest->item_type === InventoryBorrowRequest::TYPE_ASSET && $borrowRequest->asset) {
                $assetUpdates = [];
                if ($validated['return_condition'] === InventoryBorrowRequest::RETURN_DAMAGED) {
                    $assetUpdates['condition'] = InventoryAsset::CONDITION_DAMAGED;
                    $assetUpdates['status'] = InventoryAsset::STATUS_UNDER_REPAIR;
                } elseif ($validated['return_condition'] === InventoryBorrowRequest::RETURN_MISSING) {
                    $assetUpdates['status'] = InventoryAsset::STATUS_MISSING;
                } else {
                    $assetUpdates['status'] = InventoryAsset::STATUS_AVAILABLE;
                }
                $borrowRequest->asset->update($assetUpdates);
            }

            if ($borrowRequest->item_type === InventoryBorrowRequest::TYPE_STOCK && $borrowRequest->stockItem && $quantityReturned > 0) {
                $borrowRequest->stockItem->update([
                    'quantity_issued' => max(0, $borrowRequest->stockItem->quantity_issued - $quantityReturned),
                    'quantity_remaining' => $borrowRequest->stockItem->quantity_remaining + $quantityReturned,
                ]);
            }

            $borrowRequest->update([
                'status' => InventoryBorrowRequest::STATUS_RETURNED,
                'returned_by_user_id' => $user->id,
                'returned_at' => now(),
                'quantity_returned' => $quantityReturned,
                'return_condition' => $validated['return_condition'],
                'return_notes' => $validated['return_notes'] ?? null,
            ]);
        });

        return redirect()->route('inventory.borrowing.show', $borrowRequest)->with('success', 'Return recorded.');
    }

    private function validatedRequest(Request $request): array
    {
        $validated = $request->validate([
            'item_type' => ['required', Rule::in([InventoryBorrowRequest::TYPE_ASSET, InventoryBorrowRequest::TYPE_STOCK])],
            'asset_id' => ['nullable', 'string', 'short_id', 'exists:inventory_assets,id'],
            'stock_item_id' => ['nullable', 'string', 'short_id', 'exists:inventory_stock_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'borrower_user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'purpose' => ['required', 'string', 'max:1000'],
            'expected_return_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        if ($validated['item_type'] === InventoryBorrowRequest::TYPE_ASSET) {
            if (empty($validated['asset_id'])) {
                throw ValidationException::withMessages(['asset_id' => 'Select an asset to borrow.']);
            }
            $validated['stock_item_id'] = null;
            $validated['quantity'] = 1;
        } else {
            if (empty($validated['stock_item_id'])) {
                throw ValidationException::withMessages(['stock_item_id' => 'Select a stock item to borrow.']);
            }
            $validated['asset_id'] = null;
        }

        return $validated;
    }

    private function assertStatus(InventoryBorrowRequest $borrowRequest, string $expected): void
    {
        if ($borrowRequest->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => 'This request is not in the right state for that action.',
            ]);
        }
    }

    private function assertNotSelfReview(InventoryBorrowRequest $borrowRequest): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ((string) $borrowRequest->requested_by_user_id === (string) $user->id && ! $user->hasRole('superadmin')) {
            throw ValidationException::withMessages([
                'status' => 'You cannot approve or reject your own borrow request.',
            ]);
        }
    }

    private function formOptions(): array
    {
        return [
            'assets' => InventoryAsset::query()
                ->whereIn('status', [InventoryAsset::STATUS_AVAILABLE, InventoryAsset::STATUS_ASSIGNED])
                ->orderBy('name')
                ->get(['id', 'asset_code', 'name']),
            'stockItems' => InventoryStockItem::query()
                ->where('status', 'active')
                ->where('quantity_remaining', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'quantity_remaining', 'unit']),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
