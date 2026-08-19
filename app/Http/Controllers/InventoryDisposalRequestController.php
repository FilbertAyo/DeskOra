<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryDisposalRequest;
use App\Services\Inventory\InventoryNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryDisposalRequestController extends Controller
{
    public function __construct(
        private readonly InventoryNumberGenerator $numberGenerator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', InventoryDisposalRequest::class);

        $tab = $request->string('tab')->toString() ?: 'pending';

        $query = InventoryDisposalRequest::query()
            ->with(['asset', 'requestedBy', 'reviewedBy']);

        if ($tab === 'pending') {
            $query->where('status', InventoryDisposalRequest::STATUS_REQUESTED);
        } elseif ($tab === 'approved') {
            $query->where('status', InventoryDisposalRequest::STATUS_APPROVED);
        } elseif ($tab === 'rejected') {
            $query->where('status', InventoryDisposalRequest::STATUS_REJECTED);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'all' => InventoryDisposalRequest::query()->count(),
            'pending' => InventoryDisposalRequest::pendingReviewCount(),
            'approved' => InventoryDisposalRequest::query()->where('status', InventoryDisposalRequest::STATUS_APPROVED)->count(),
            'rejected' => InventoryDisposalRequest::query()->where('status', InventoryDisposalRequest::STATUS_REJECTED)->count(),
        ];

        return view('inventory.disposal.index', [
            'requests' => $requests,
            'tab' => $tab,
            'counts' => $counts,
            'statusOptions' => InventoryDisposalRequest::statusOptions(),
            'methodOptions' => InventoryDisposalRequest::methodOptions(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', InventoryDisposalRequest::class);

        $asset = null;
        if ($request->filled('asset_id')) {
            $asset = InventoryAsset::query()->findOrFail($request->input('asset_id'));
            $this->authorize('requestDisposal', $asset);
        }

        return view('inventory.disposal.create', [
            'asset' => $asset,
            'assets' => InventoryAsset::query()
                ->whereNotIn('status', [
                    InventoryAsset::STATUS_DISPOSED,
                    InventoryAsset::STATUS_BORROWED,
                ])
                ->orderBy('name')
                ->get(['id', 'asset_code', 'name', 'status']),
            'methodOptions' => InventoryDisposalRequest::methodOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryDisposalRequest::class);

        $validated = $request->validate([
            'asset_id' => ['required', 'string', 'short_id', 'exists:inventory_assets,id'],
            'method' => ['required', Rule::in(array_keys(InventoryDisposalRequest::methodOptions()))],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $asset = InventoryAsset::query()->findOrFail($validated['asset_id']);
        $this->authorize('requestDisposal', $asset);

        if ($asset->isDisposed() || $asset->status === InventoryAsset::STATUS_BORROWED) {
            throw ValidationException::withMessages([
                'asset_id' => 'This asset cannot be disposed in its current status.',
            ]);
        }

        if ($asset->hasPendingDisposal()) {
            throw ValidationException::withMessages([
                'asset_id' => 'This asset already has a pending disposal request.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $disposal = InventoryDisposalRequest::query()->create([
            ...$validated,
            'request_number' => $this->numberGenerator->generate(
                InventoryDisposalRequest::class,
                'request_number',
                'DSP'
            ),
            'status' => InventoryDisposalRequest::STATUS_REQUESTED,
            'requested_by_user_id' => $user->id,
        ]);

        return redirect()
            ->route('inventory.disposal.show', $disposal)
            ->with('success', 'Disposal request submitted for review.');
    }

    public function show(InventoryDisposalRequest $disposal)
    {
        $this->authorize('view', $disposal);

        $disposal->load(['asset.location', 'asset.responsibleUser', 'requestedBy', 'reviewedBy']);

        return view('inventory.disposal.show', [
            'disposal' => $disposal,
            'statusOptions' => InventoryDisposalRequest::statusOptions(),
            'methodOptions' => InventoryDisposalRequest::methodOptions(),
        ]);
    }

    public function approve(Request $request, InventoryDisposalRequest $disposal)
    {
        $this->authorize('approve', $disposal);
        $this->assertStatus($disposal, InventoryDisposalRequest::STATUS_REQUESTED);

        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($disposal, $validated, $user) {
            $asset = $disposal->asset()->lockForUpdate()->firstOrFail();

            if ($asset->isDisposed() || $asset->status === InventoryAsset::STATUS_BORROWED) {
                throw ValidationException::withMessages([
                    'status' => 'This asset can no longer be disposed.',
                ]);
            }

            $disposal->update([
                'status' => InventoryDisposalRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
                'disposed_at' => now(),
            ]);

            $asset->update([
                'status' => InventoryAsset::STATUS_DISPOSED,
            ]);
        });

        return redirect()
            ->route('inventory.disposal.show', $disposal)
            ->with('success', 'Disposal approved. The asset is locked and kept for history.');
    }

    public function reject(Request $request, InventoryDisposalRequest $disposal)
    {
        $this->authorize('reject', $disposal);
        $this->assertStatus($disposal, InventoryDisposalRequest::STATUS_REQUESTED);

        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $disposal->update([
            'status' => InventoryDisposalRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        return redirect()
            ->route('inventory.disposal.show', $disposal)
            ->with('success', 'Disposal request rejected.');
    }

    private function assertStatus(InventoryDisposalRequest $disposal, string $expected): void
    {
        if ($disposal->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => 'This request is not in the right state for that action.',
            ]);
        }
    }
}
