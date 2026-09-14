<?php

namespace App\Http\Controllers;

use App\Models\OfficialLetter;
use App\Models\User;
use App\Services\OfficialLetterDownloadAuthorizationService;
use App\Services\OfficialLetterReferenceService;
use App\Services\SystemSettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class OfficialLetterController extends Controller
{
    public function __construct(
        private readonly OfficialLetterReferenceService $referenceService,
        private readonly OfficialLetterDownloadAuthorizationService $downloadAuthorizationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OfficialLetter::class);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canManage = $user->can(OfficialLetter::PERMISSION_MANAGE);
        $canAuthorizeDownloads = $user->can(OfficialLetter::PERMISSION_AUTHORIZE_DOWNLOADS);
        $tab = $canAuthorizeDownloads && $request->input('tab') === 'authorizations'
            ? 'authorizations'
            : 'mine';

        $query = OfficialLetter::query()
            ->with([
                'creator',
                'signatory',
                'sharedUsers:id,name',
                'latestDownloadRequest.requester:id,name',
            ]);

        if ($tab === 'authorizations') {
            $query->withDownloadRequests()
                ->orderByRaw($this->pendingDownloadRequestSortSql())
                ->orderByDesc('letter_date')
                ->orderByDesc('created_at');
        } else {
            $query->visibleTo($user)
                ->orderByDesc('letter_date')
                ->orderByDesc('created_at');
        }

        if ($request->filled('department_code')) {
            $query->where('department_code', strtoupper((string) $request->input('department_code')));
        }

        if ($request->filled('year')) {
            $query->whereYear('letter_date', $request->integer('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('letter_date', $request->integer('month'));
        }

        $letters = $query->paginate(15)->withQueryString();
        $departments = config('official-letters.departments', []);
        $yearsQuery = OfficialLetter::query();
        if ($tab === 'authorizations') {
            $yearsQuery->withDownloadRequests();
        } else {
            $yearsQuery->visibleTo($user);
        }
        $years = $yearsQuery
            ->orderByDesc('letter_date')
            ->pluck('letter_date')
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->unique()
            ->values();

        $pendingAuthorizationCount = $canAuthorizeDownloads
            ? OfficialLetter::pendingDownloadAuthorizationCount()
            : 0;

        return view('official-letters.index', compact(
            'letters',
            'departments',
            'years',
            'canManage',
            'canAuthorizeDownloads',
            'tab',
            'pendingAuthorizationCount',
            'user',
        ));
    }

    public function create()
    {
        $this->authorize('create', OfficialLetter::class);

        return view('official-letters.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $this->authorize('create', OfficialLetter::class);

        $lock = Cache::lock('official-letter-store:'.Auth::id(), 15);

        if (! $lock->get()) {
            return redirect()
                ->route('official-letters.index')
                ->with('error', 'Your letter is already being created. Please wait a moment and check the list.');
        }

        try {
            $validated = $this->validateLetter($request);
            $letterDate = Carbon::parse($validated['letter_date'])->startOfDay();

            $allocated = $this->referenceService->allocate($validated['department_code'], $letterDate);

            $letter = new OfficialLetter($validated);
            $letter->created_by = Auth::id();
            $letter->reference_number = $allocated['reference_number'];
            $letter->sequence = $allocated['sequence'];
            $letter->save();
        } finally {
            $lock->release();
        }

        return redirect()
            ->route('official-letters.show', $letter)
            ->with('success', 'Official letter created with reference '.$letter->reference_number.'.');
    }

    public function show(OfficialLetter $officialLetter)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->can('view', $officialLetter)) {
            return redirect()->route('official-letters.verify', $officialLetter);
        }

        $this->authorize('view', $officialLetter);

        $officialLetter->load([
            'creator',
            'signatory',
            'sharedUsers:id,name',
            'latestDownloadRequest.requester:id,name',
            'latestDownloadRequest.decider:id,name',
        ]);
        $shareableUsers = $this->shareableUsersFor($user, $officialLetter);
        $canShare = $user->can('share', $officialLetter);
        $canUpdate = $user->can('update', $officialLetter);
        $canDelete = $user->can('delete', $officialLetter);
        $canDownload = $user->can('download', $officialLetter);
        $canRequestDownload = $user->can('requestDownload', $officialLetter);
        $canAuthorizeDownload = $user->can('authorizeDownload', $officialLetter);
        $isSharedWithMe = ! $officialLetter->isOwnedBy($user) && $officialLetter->isSharedWith($user);
        $isAuthorizerReview = $officialLetter->userCanReviewDownloadRequest($user)
            && ! $officialLetter->userHasFullAccess($user);

        return view('official-letters.show', [
            'letter' => $officialLetter,
            'shareableUsers' => $shareableUsers,
            'canShare' => $canShare,
            'canUpdate' => $canUpdate,
            'canDelete' => $canDelete,
            'canDownload' => $canDownload,
            'canRequestDownload' => $canRequestDownload,
            'canAuthorizeDownload' => $canAuthorizeDownload,
            'isSharedWithMe' => $isSharedWithMe,
            'isAuthorizerReview' => $isAuthorizerReview,
        ]);
    }

    public function verify(OfficialLetter $officialLetter)
    {
        $this->authorize('verify', $officialLetter);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->can('view', $officialLetter)) {
            return redirect()->route('official-letters.show', $officialLetter);
        }

        $officialLetter->load(['creator:id,name', 'signatory:id,name,title']);

        return view('official-letters.verify', [
            'letter' => $officialLetter,
        ]);
    }

    public function share(Request $request, OfficialLetter $officialLetter)
    {
        $this->authorize('share', $officialLetter);

        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        $validated = $request->validate([
            'shared_user_ids' => ['nullable', 'array'],
            'shared_user_ids.*' => ['nullable', 'string', 'short_id', 'exists:users,id'],
        ]);

        $desiredIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $validated['shared_user_ids'] ?? []),
            static fn ($id) => $id !== '' && $id !== (string) $officialLetter->created_by
        )));

        $existingIds = $officialLetter->sharedUsers()
            ->pluck('users.id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        $syncPayload = [];
        foreach ($desiredIds as $userId) {
            $syncPayload[$userId] = ['shared_by' => $actor->id];
        }

        $officialLetter->sharedUsers()->sync($syncPayload);

        $addedIds = array_values(array_diff($desiredIds, $existingIds));
        if ($addedIds !== []) {
            $recipients = User::query()
                ->active()
                ->whereIn('id', $addedIds)
                ->get();

            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new \App\Notifications\OfficialLetterSharedNotification($officialLetter, $actor)
            );
        }

        return redirect()
            ->route('official-letters.show', $officialLetter)
            ->with('success', 'Letter sharing updated.'.($addedIds !== [] ? ' Newly shared staff were notified by email.' : ''));
    }

    public function edit(OfficialLetter $officialLetter)
    {
        $this->authorize('update', $officialLetter);

        return view('official-letters.edit', array_merge(
            $this->formOptions(),
            ['letter' => $officialLetter],
        ));
    }

    public function update(Request $request, OfficialLetter $officialLetter)
    {
        $this->authorize('update', $officialLetter);

        $validated = $this->validateLetter($request);
        $letterDate = Carbon::parse($validated['letter_date'])->startOfDay();

        $departmentChanged = strtoupper($validated['department_code']) !== strtoupper($officialLetter->department_code);
        $monthChanged = $letterDate->format('Y-m') !== $officialLetter->letter_date->format('Y-m');

        if ($departmentChanged || $monthChanged) {
            $allocated = $this->referenceService->allocate($validated['department_code'], $letterDate);
            $officialLetter->reference_number = $allocated['reference_number'];
            $officialLetter->sequence = $allocated['sequence'];
        } elseif ($letterDate->format('Y-m-d') !== $officialLetter->letter_date->format('Y-m-d')) {
            $officialLetter->reference_number = $this->referenceService->formatReference(
                strtoupper($validated['department_code']),
                strtoupper($letterDate->format('M')),
                $letterDate->format('y'),
                $letterDate->format('d'),
                str_pad((string) $officialLetter->sequence, 3, '0', STR_PAD_LEFT),
            );
        }

        $officialLetter->fill($validated);
        $officialLetter->save();

        return redirect()
            ->route('official-letters.show', $officialLetter)
            ->with('success', 'Official letter updated.');
    }

    public function destroy(OfficialLetter $officialLetter)
    {
        $this->authorize('delete', $officialLetter);

        $officialLetter->delete();

        return redirect()
            ->route('official-letters.index')
            ->with('success', 'Official letter deleted.');
    }

    public function requestDownload(Request $request, OfficialLetter $officialLetter)
    {
        $this->authorize('requestDownload', $officialLetter);

        $validated = $request->validate([
            'request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->downloadAuthorizationService->requestDownload(
            $officialLetter,
            $user,
            $this->optionalNote($validated['request_note'] ?? null),
        );

        return redirect()
            ->route('official-letters.show', $officialLetter)
            ->with('success', 'Download authorization requested. You will be notified when it is reviewed.');
    }

    public function authorizeDownload(Request $request, OfficialLetter $officialLetter)
    {
        $this->authorize('authorizeDownload', $officialLetter);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->downloadAuthorizationService->authorize(
            $officialLetter,
            $user,
            $this->optionalNote($validated['decision_note'] ?? null),
        );

        return redirect()
            ->route('official-letters.show', $officialLetter)
            ->with('success', 'Download authorized. The requester has been notified.');
    }

    public function rejectDownload(Request $request, OfficialLetter $officialLetter)
    {
        $this->authorize('rejectDownload', $officialLetter);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $this->downloadAuthorizationService->reject(
            $officialLetter,
            $user,
            $this->optionalNote($validated['decision_note'] ?? null),
        );

        return redirect()
            ->route('official-letters.show', $officialLetter)
            ->with('success', 'Download request rejected. The requester has been notified.');
    }

    public function download(OfficialLetter $officialLetter)
    {
        $this->authorize('download', $officialLetter);

        $officialLetter->loadMissing('signatory');

        $settings = app(SystemSettingService::class);
        $logoAbsolutePath = $settings->formsLogoAbsolutePath();
        $calibriRegularPath = resource_path('fonts/Carlito-Regular.ttf');
        $calibriBoldPath = resource_path('fonts/Carlito-Bold.ttf');
        $letterhead = config('official-letters.letterhead', []);
        $signatorySignaturePath = null;
        if ($officialLetter->usesDigitalSignature()) {
            $signatorySignaturePath = $this->resolveUserSignatureAbsolutePath($officialLetter->signatory);
        }

        $letterUrl = route('official-letters.show', $officialLetter);
        $qrCodeDataUri = null;
        try {
            $qrResult = (new \Endroid\QrCode\Writer\PngWriter)->write(new \Endroid\QrCode\QrCode(
                data: $letterUrl,
                size: 160,
                margin: 2,
            ));
            $qrCodeDataUri = 'data:image/png;base64,'.base64_encode($qrResult->getString());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to generate official letter PDF QR code', [
                'letter_id' => $officialLetter->id,
                'error' => $e->getMessage(),
            ]);
        }

        // DomPDF sometimes fails on data-URI images; also write a temp PNG fallback.
        $qrCodeAbsolutePath = null;
        if (is_string($qrCodeDataUri) && $qrCodeDataUri !== '') {
            $qrCodeAbsolutePath = storage_path('app/temp/official-letter-qr-'.$officialLetter->id.'.png');
            $dir = dirname($qrCodeAbsolutePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($qrCodeAbsolutePath, base64_decode(substr($qrCodeDataUri, strlen('data:image/png;base64,')), true));
        }

        $pdf = Pdf::loadView('official-letters.pdf', [
            'letter' => $officialLetter,
            'logoAbsolutePath' => $logoAbsolutePath,
            'calibriRegularPath' => $calibriRegularPath,
            'calibriBoldPath' => $calibriBoldPath,
            'letterhead' => $letterhead,
            'qrCodeDataUri' => $qrCodeDataUri,
            'qrCodeAbsolutePath' => $qrCodeAbsolutePath,
            'signatorySignaturePath' => $signatorySignaturePath,
            'footerPhoneIconPath' => public_path('assets/images/letterhead/phone.png'),
            'footerEmailIconPath' => public_path('assets/images/letterhead/email.png'),
            'footerGlobeIconPath' => public_path('assets/images/letterhead/globe.png'),
        ])->setPaper('a4', 'portrait');

        $safeName = str_replace(['/', ' '], ['-', '_'], $officialLetter->reference_number);

        $response = $pdf->download($safeName.'.pdf');

        if (is_string($qrCodeAbsolutePath) && is_file($qrCodeAbsolutePath)) {
            @unlink($qrCodeAbsolutePath);
        }

        return $response;
    }

    private function optionalNote(?string $note): ?string
    {
        $trimmed = trim((string) $note);

        return $trimmed === '' ? null : $trimmed;
    }

    private function pendingDownloadRequestSortSql(): string
    {
        return "(
            select case when status = 'pending' then 0 else 1 end
            from official_letter_download_requests
            where official_letter_id = official_letters.id
            order by created_at desc, id desc
            limit 1
        )";
    }

    /**
     * Resolve an absolute filesystem path for a user's uploaded signature image.
     */
    private function resolveUserSignatureAbsolutePath(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $signature = trim((string) $user->signature);
        if ($signature === '') {
            return null;
        }

        $candidates = [
            public_path($signature),
            public_path(ltrim($signature, '/')),
            storage_path('app/public/'.ltrim(preg_replace('#^storage/#', '', $signature) ?? $signature, '/')),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLetter(Request $request): array
    {
        $departmentCodes = array_keys(config('official-letters.departments', []));
        $salutations = config('official-letters.salutations', []);
        $closings = config('official-letters.closings', []);

        $validated = $request->validate([
            'department_code' => ['required', 'string', Rule::in($departmentCodes)],
            'letter_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:255'],
            'recipient' => ['required', 'string', 'max:5000'],
            'subject' => ['required', 'string', 'max:500'],
            'salutation' => ['required', 'string', Rule::in($salutations)],
            'body' => ['required', 'string', 'max:100000'],
            'closing' => ['required', 'string', Rule::in($closings)],
            'signatory_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'signature_mode' => [
                'required',
                'string',
                Rule::in([
                    OfficialLetter::SIGNATURE_MODE_DIGITAL,
                    OfficialLetter::SIGNATURE_MODE_MANUAL,
                ]),
            ],
        ]);

        $signatory = User::query()->findOrFail($validated['signatory_id']);
        $validated['department_code'] = strtoupper($validated['department_code']);
        $validated['name'] = $this->optionalNote($validated['name'] ?? null);
        $validated['recipient'] = mb_strtoupper($validated['recipient'], 'UTF-8');
        $validated['subject'] = mb_strtoupper($validated['subject'], 'UTF-8');
        $validated['body'] = $this->sanitizeBodyHtml($validated['body']);
        $validated['signatory_name'] = (string) $signatory->name;
        $validated['signatory_title'] = (string) ($signatory->title ?? '');

        unset($validated['reference_number'], $validated['sequence']);

        return $validated;
    }

    private function sanitizeBodyHtml(string $html): string
    {
        $cleaned = strip_tags(
            $html,
            '<p><br><br/><strong><b><em><i><u><ul><ol><li><span><div>'
        );

        $cleaned = preg_replace('/\son\w+="[^"]*"/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/\son\w+=\'[^\']*\'/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/javascript:/i', '', $cleaned) ?? $cleaned;

        if (trim(strip_tags($cleaned)) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'body' => ['Letter body cannot be empty.'],
            ]);
        }

        return $cleaned;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'departments' => config('official-letters.departments', []),
            'salutations' => config('official-letters.salutations', []),
            'closings' => config('official-letters.closings', []),
            'signatories' => $this->signatoryOptions(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function signatoryOptions()
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'title']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function shareableUsersFor(User $actor, OfficialLetter $letter)
    {
        return User::query()
            ->active()
            ->select('id', 'name')
            ->where('id', '!=', $letter->created_by)
            ->orderBy('name')
            ->get();
    }
}
