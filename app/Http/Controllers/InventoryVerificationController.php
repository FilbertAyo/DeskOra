<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\InventoryAsset;
use App\Models\InventoryLocation;
use App\Models\InventoryVerificationResult;
use App\Models\InventoryVerificationSession;
use App\Models\User;
use App\Services\Inventory\InventoryNumberGenerator;
use App\Services\Inventory\InventoryVerificationProcessor;
use App\Support\InventoryHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryVerificationController extends Controller
{
    public function __construct(
        private readonly InventoryNumberGenerator $numberGenerator,
        private readonly InventoryVerificationProcessor $processor,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', InventoryVerificationSession::class);

        $sessions = InventoryVerificationSession::query()
            ->with(['location', 'department', 'startedBy'])
            ->withCount([
                'results',
                'results as missing_count' => fn ($query) => $query->where('result', InventoryVerificationResult::RESULT_MISSING),
                'results as pending_count' => fn ($query) => $query->where('result', InventoryVerificationResult::RESULT_PENDING),
            ])
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('inventory.verification.index', [
            'sessions' => $sessions,
            'statusOptions' => InventoryVerificationSession::statusOptions(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', InventoryVerificationSession::class);

        return view('inventory.verification.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('create', InventoryVerificationSession::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
            'department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['location_id'] = InventoryHierarchy::resolveLocationId($request) ?: ($validated['location_id'] ?? null);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $session = DB::transaction(function () use ($validated, $user) {
            $session = InventoryVerificationSession::query()->create([
                'session_number' => $this->numberGenerator->generate(
                    InventoryVerificationSession::class,
                    'session_number',
                    'VFY'
                ),
                'name' => $validated['name'],
                'location_id' => $validated['location_id'] ?: null,
                'department_id' => $validated['department_id'] ?: null,
                'notes' => $validated['notes'] ?? null,
                'status' => InventoryVerificationSession::STATUS_OPEN,
                'started_by_user_id' => $user->id,
                'started_at' => now(),
            ]);

            $assets = InventoryAsset::query()
                ->where('status', '!=', InventoryAsset::STATUS_DISPOSED)
                ->when($session->location_id, function ($query) use ($session) {
                    InventoryHierarchy::applyLocationFilter($query, $session->location_id);
                })
                ->when($session->department_id, fn ($query) => $query->where('department_id', $session->department_id))
                ->get(['id']);

            foreach ($assets as $asset) {
                InventoryVerificationResult::query()->create([
                    'session_id' => $session->id,
                    'asset_id' => $asset->id,
                    'result' => InventoryVerificationResult::RESULT_PENDING,
                ]);
            }

            return $session;
        });

        return redirect()
            ->route('inventory.verification.show', $session)
            ->with('success', 'Verification session started. Record results for each asset.');
    }

    public function show(InventoryVerificationSession $verification)
    {
        $this->authorize('view', $verification);

        $session = $verification;
        $session->load(['location.parent', 'department', 'startedBy', 'completedBy']);
        $results = $session->results()
            ->with(['asset.location.parent', 'asset.responsibleUser', 'recordedLocation.parent', 'recordedResponsibleUser'])
            ->get();

        return view('inventory.verification.show', array_merge($this->formOptions(), [
            'session' => $session,
            'results' => $results,
            'resultOptions' => InventoryVerificationResult::resultOptions(),
            'statusOptions' => InventoryVerificationSession::statusOptions(),
        ]));
    }

    public function updateResults(Request $request, InventoryVerificationSession $verification)
    {
        $this->authorize('update', $verification);

        $validated = $request->validate([
            'results' => ['required', 'array'],
            'results.*.result' => ['required', Rule::in(array_keys(InventoryVerificationResult::resultOptions()))],
            'results.*.recorded_location_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
            'results.*.recorded_responsible_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'results.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($verification, $validated, $user) {
            foreach ($validated['results'] as $resultId => $payload) {
                $result = $verification->results()->whereKey($resultId)->first();
                if (! $result) {
                    continue;
                }

                if ($payload['result'] === InventoryVerificationResult::RESULT_LOCATION_CHANGED
                    && empty($payload['recorded_location_id'])) {
                    throw ValidationException::withMessages([
                        "results.{$resultId}.recorded_location_id" => 'Choose the new location.',
                    ]);
                }

                if ($payload['result'] === InventoryVerificationResult::RESULT_PERSON_CHANGED
                    && empty($payload['recorded_responsible_user_id'])) {
                    throw ValidationException::withMessages([
                        "results.{$resultId}.recorded_responsible_user_id" => 'Choose the new responsible person.',
                    ]);
                }

                $result->update([
                    'result' => $payload['result'],
                    'recorded_location_id' => $payload['recorded_location_id'] ?: null,
                    'recorded_responsible_user_id' => $payload['recorded_responsible_user_id'] ?: null,
                    'notes' => $payload['notes'] ?? null,
                    'recorded_by_user_id' => $user->id,
                    'recorded_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('inventory.verification.show', $verification)
            ->with('success', 'Verification results saved.');
    }

    public function complete(InventoryVerificationSession $verification)
    {
        $this->authorize('complete', $verification);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->processor->complete($verification, $user);

        return redirect()
            ->route('inventory.verification.show', $verification)
            ->withGooeyToast('success', 'Verification completed', [
                'message' => 'Missing, damaged, location, and person changes were applied.',
            ]);
    }

    private function formOptions(): array
    {
        return [
            'locations' => InventoryLocation::query()->where('status', 'active')->with('parent')->orderBy('name')->get(['id', 'name', 'parent_id']),
            'departments' => Department::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
            'inventoryPickerData' => InventoryHierarchy::pickerPayload(),
        ];
    }
}
