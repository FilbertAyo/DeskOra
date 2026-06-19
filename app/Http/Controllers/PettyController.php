<?php

namespace App\Http\Controllers;

use App\Mail\FirstApprovalMail;
use App\Mail\LastApprovalMail;
use App\Mail\PettyRequestMail;
use App\Mail\RejectMail;
use App\Mail\ResubmitMail;
use App\Mail\SuccessPayment;
use App\Models\Department;
use App\Models\Deposit;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionItem;
use App\Models\RequisitionRequest;
use App\Models\User;
use App\Models\YearPlanActivity;
use App\Services\NotificationService;
use App\Services\RequisitionNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use NumberToWords\NumberToWords;

class PettyController extends Controller
{
    public function __construct(
        private readonly RequisitionNotificationService $requisitionNotifications,
    ) {}

    private function buildRequestMailArgs(FinanceRequest $request): array
    {
        $requester = User::find($request->user_id);
        $name = $requester?->name ?? 'User';
        $reason = $request->request_for ?: 'request';
        $publicId = (string) $request->id;

        return [$name, $reason, $publicId];
    }

    private function notifyApprovers(string $permission, ?string $departmentId, object $mailable, string $smsMessage): void
    {
        $scopeIds = $this->resolveDepartmentScopeIds($departmentId);
        if ($scopeIds === []) {
            return;
        }

        $users = User::permission($permission)
            ->whereIn('department_id', $scopeIds)
            ->get(['id', 'email', 'phone', 'notification_channel']);

        if ($users->isEmpty()) {
            return;
        }

        $emails = [];
        $notifier = app(NotificationService::class);
        foreach ($users as $user) {
            $channel = $user->notification_channel ?: 'sms';
            if (in_array($channel, ['email', 'both']) && ! empty($user->email)) {
                $emails[] = $user->email;
            }
            if (in_array($channel, ['sms', 'both']) && ! empty($user->phone)) {
                try {
                    $notifier->sendSms($user->phone, $smsMessage);
                } catch (\Throwable $e) {
                    Log::error('Failed to send SMS to approver', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }
        }

        if (! empty($emails)) {
            $this->trySendMail(array_values(array_unique($emails)), $mailable);
        }
    }

    private function trySendMail(array|string $recipients, object $mailable): bool
    {
        if (empty($recipients)) {
            return false;
        }
        try {
            Mail::to($recipients)->send($mailable);

            return true;
        } catch (\Throwable $e) {
            Log::error('PettyController: failed to send email', [
                'recipients' => $recipients,
                'mailable' => get_class($mailable),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function generateUniqueRequestNo(): string
    {
        do {
            $code = strtoupper(Str::random(2)).rand(100, 999);
        } while (FinanceRequest::where('request_no', $code)->exists());

        return $code;
    }

    private function ensureUserHasSignature(): ?\Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || empty($user->signature)) {
            return redirect()->back()->with('error', 'Please upload your signature in profile settings before signing this requisition.');
        }

        return null;
    }

    private function canSignRequest(FinanceRequest $request, string $stage): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user && $request->userCanActAtStage($user, $stage);
    }

    private function firstAccessibleApprovalStage(\App\Models\User $user): string
    {
        $stages = FinanceRequest::accessibleStagesFor($user);

        abort_if($stages === [], 403, 'You are not allowed to access approvals.');

        return $stages[0];
    }

    public function requests_list(\Illuminate\Http\Request $httpRequest)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $stage = $httpRequest->query('stage');

        if (! is_string($stage) || $stage === '') {
            return redirect()->route('petty.list', ['stage' => $this->firstAccessibleApprovalStage($user)]);
        }

        if (! in_array($stage, FinanceRequest::APPROVAL_STAGES, true)) {
            abort(404);
        }

        if (! FinanceRequest::userCanAccessStage($user, $stage)) {
            abort(403, 'You are not allowed to access this approval stage.');
        }

        $requests = FinanceRequest::with(['user', 'pettyDetails', 'requisitionDetails', 'approvals.actor'])
            ->visibleForUserAtStage($user, $stage)
            ->when(
                is_string($typeFilter = $httpRequest->query('type')) && FinanceRequest::requestTypesForFilter($typeFilter) !== null,
                fn ($query) => $query->whereIn('request_type', FinanceRequest::requestTypesForFilter($typeFilter))
            )
            ->orderByDesc('created_at')
            ->get()
            ->sortByDesc(fn (FinanceRequest $request) => $request->userCanActAtStage($user, $stage) ? 1 : 0)
            ->values();

        $stageLabel = FinanceRequest::stageLabel($stage);
        $typeFilter = $httpRequest->query('type');

        return view('requests.approval.index', compact('requests', 'stage', 'stageLabel', 'typeFilter'));
    }

    private function isRequisitionFullySigned(FinanceRequest $request): bool
    {
        return $request->reviewed_at !== null && $request->approved_at !== null && $request->authorized_at !== null;
    }

    private function createApproval(FinanceRequest $request, string $step, string $action, ?string $comment = null): RequestApproval
    {
        return RequestApproval::create([
            'request_id' => $request->id,
            'step' => $step,
            'action' => $action,
            'actor_id' => Auth::id(),
            'comment' => $comment,
            'acted_at' => now(),
        ]);
    }

    public function index()
    {
        FinanceRequest::query()
            ->where('status', 'resubmission')
            ->where('updated_at', '<', now()->subDay())
            ->update(['status' => 'rejected']);

        $requests = FinanceRequest::with(['pettyDetails'])
            ->where('user_id', Auth::id())
            ->where('request_type', '!=', 'Staff Requisition')
            ->orderByDesc('created_at')
            ->get();

        return view('requests.petty.index', compact('requests'));
    }

    public function create()
    {
        return view('requests.petty.create');
    }

    public function createRequisition()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $planActivities = $this->requisitionPlanActivitiesForUser($user);

        return view('requests.requisition.create', compact('planActivities'));
    }

    public function store(Request $request)
    {
        $requestType = (string) $request->input('request_type');

        if ($requestType === 'Staff Requisition') {
            return $this->storeStaffRequisition($request);
        }

        $baseRules = [
            'user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'department_id' => ['required', 'string', 'short_id', 'exists:departments,id'],
            'request_type' => 'required|string|in:Petty Cash,Reimbursement,Staff Requisition',
        ];
        $rules = array_merge($baseRules, [
            'request_for' => 'required|string|in:Sales Delivery,Transport,Office Supplies',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
        ]);
        $validated = $request->validate($rules);

        DB::transaction(function () use ($requestType, $validated) {
            $requestRow = FinanceRequest::create([
                'request_no' => $this->generateUniqueRequestNo(),
                'request_type' => $requestType,
                'user_id' => $validated['user_id'],
                'department_id' => $validated['department_id'],
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            PettyRequest::create([
                'request_id' => $requestRow->id,
                'request_for' => $validated['request_for'],
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
            ]);

            RequestApproval::create([
                'request_id' => $requestRow->id,
                'step' => 'system',
                'action' => 'created',
                'actor_id' => $requestRow->user_id,
                'acted_at' => now(),
            ]);

            [$name, $reason, $encodedId] = $this->buildRequestMailArgs($requestRow->fresh(['pettyDetails', 'requisitionDetails']));
            $sms = "New request from {$name} for {$reason}. Ref: {$encodedId}";
            $this->notifyApprovers('first pettycash approval', $requestRow->department_id, new PettyRequestMail($name, $reason, $encodedId), $sms);
        });

        return redirect()->route('petty.index')->with('success', 'Request created successfully!');
    }

    private function storeStaffRequisition(Request $request): \Illuminate\Http\RedirectResponse
    {
        $isDraft = $this->isRequisitionDraftAction($request);
        $baseRules = [
            'user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            'department_id' => ['required', 'string', 'short_id', 'exists:departments,id'],
            'request_type' => 'required|string|in:Staff Requisition',
            'action' => 'nullable|string|in:draft,submit',
        ];
        $validated = $request->validate(array_merge(
            $baseRules,
            $isDraft ? $this->requisitionDraftValidationRules() : $this->requisitionSubmitValidationRules()
        ));

        $items = $this->normalizeRequisitionItems($request->input('items', []));

        if (! $isDraft && $items === []) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one line item before submitting.',
            ]);
        }

        $requestRow = DB::transaction(function () use ($validated, $items, $isDraft) {
            $requestRow = FinanceRequest::create([
                'request_no' => $this->generateUniqueRequestNo(),
                'request_type' => 'Staff Requisition',
                'user_id' => $validated['user_id'],
                'department_id' => $validated['department_id'],
                'status' => $isDraft ? 'draft' : 'pending',
                'submitted_at' => $isDraft ? null : now(),
            ]);

            $this->syncRequisitionDetails($requestRow, $validated, $items);

            if (! $isDraft) {
                $this->finalizeRequisitionSubmission($requestRow);
            }

            return $requestRow;
        });

        if ($isDraft) {
            return redirect()
                ->route('requisition.edit', $requestRow)
                ->with('success', 'Draft saved. You can continue editing and submit when ready.');
        }

        return redirect()->route('requisition.index')->with('success', 'Requisition submitted successfully!');
    }

    public function show(FinanceRequest $petty)
    {
        $request = $petty->load(['user', 'department', 'pettyDetails', 'requisitionDetails', 'requisitionItems', 'approvals.user']);
        $approval_logs = $request->approvals()->with('user')->orderBy('acted_at')->get();

        $numberToWords = new NumberToWords;
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $amountWords = ucwords($numberTransformer->toWords($request->amount));
        $amountInWords = 'TZS '.$amountWords;
        $verifiedBy = $approval_logs->where('action', 'approved')->values()->get(0);
        $approvedBy = $approval_logs->where('action', 'approved')->values()->get(1);
        $gm = User::where('email', 'gm@marstanzania.com')->first();

        return view('requests.petty.view', compact('request', 'amountInWords', 'approval_logs', 'verifiedBy', 'approvedBy', 'gm'));
    }

    public function requestsCashier()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $scopeIds = $user->departmentScopeIds();
        $parentScopeIds = Department::whereIn('id', $scopeIds)->pluck('parent_id')->filter()->values()->all();
        $allScopeIds = array_values(array_unique(array_merge($scopeIds, $parentScopeIds)));
        $requests = FinanceRequest::with(['user', 'pettyDetails'])
            ->whereIn('department_id', $allScopeIds)
            ->whereIn('request_type', ['Petty Cash', 'Reimbursement'])
            ->whereIn('status', ['approved', 'paid'])
            ->orderByDesc('created_at')
            ->get();

        return view('requests.finance.payments', compact('requests'));
    }

    public function all_requests()
    {
        $requests = FinanceRequest::with(['user', 'pettyDetails', 'requisitionDetails'])
            ->orderByDesc('created_at')
            ->get();

        return view('requests.approval.index', compact('requests'));
    }

    public function request_show(\Illuminate\Http\Request $httpRequest, FinanceRequest $petty)
    {
        $request = $petty->load(['user', 'department', 'pettyDetails', 'requisitionDetails', 'requisitionItems', 'approvals.user']);
        $latest = $request->approvals()->where('actor_id', Auth::id())->latest('acted_at')->first();
        $approval_logs = $request->approvals()->with('user')->orderBy('acted_at')->get();
        $approval = optional($latest)->action;
        $verifiedBy = $approval_logs->where('action', 'approved')->values()->get(0);
        $approvedBy = $approval_logs->where('action', 'approved')->values()->get(1);
        $gm = User::where('email', 'gm@marstanzania.com')->first();

        $numberToWords = new NumberToWords;
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $amountWords = ucwords($numberTransformer->toWords($request->amount));
        $amountInWords = 'TZS '.$amountWords;

        $returnStage = $httpRequest->query('stage');
        if (! is_string($returnStage) || ! in_array($returnStage, FinanceRequest::APPROVAL_STAGES, true)) {
            $returnStage = in_array($request->approvalStage(), FinanceRequest::APPROVAL_STAGES, true)
                ? $request->approvalStage()
                : 'review';
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canActOnRequest = $request->userCanActAtStage($user, $request->approvalStage());

        return view('requests.approval.details', compact(
            'request',
            'amountInWords',
            'approval',
            'approval_logs',
            'verifiedBy',
            'approvedBy',
            'gm',
            'returnStage',
            'canActOnRequest'
        ));
    }

    public function f_approve(FinanceRequest $petty)
    {
        if ($redirect = $this->ensureUserHasSignature()) {
            return $redirect;
        }
        if (! $this->canSignRequest($petty, 'review')) {
            return redirect()->back()->with('error', 'You are not allowed to review this request at this stage.');
        }

        $this->createApproval($petty, 'review', 'approved');
        $petty->update(['status' => 'processing']);

        if ($petty->request_type === 'Staff Requisition') {
            $this->requisitionNotifications->notifyStageApprovers($petty, 'reviewed');
        } else {
            [$name, $reason, $encodedId] = $this->buildRequestMailArgs($petty);
            $this->notifyApprovers('last pettycash approval', $petty->department_id, new FirstApprovalMail($name, $reason, $encodedId), "Petty cash request {$encodedId} has been reviewed and is awaiting approval.");
        }

        return redirect()->back()->with('success', 'Request reviewed successfully.');
    }

    public function l_approve(FinanceRequest $petty)
    {
        if ($redirect = $this->ensureUserHasSignature()) {
            return $redirect;
        }
        if (! $this->canSignRequest($petty, 'approve')) {
            return redirect()->back()->with('error', 'You are not allowed to approve this request at this stage.');
        }

        $this->createApproval($petty, 'approve', 'approved');
        $petty->update(['status' => 'processing']);

        if ($petty->request_type === 'Staff Requisition') {
            $this->requisitionNotifications->notifyStageApprovers($petty, 'approved');
        } else {
            [$name, $reason, $encodedId] = $this->buildRequestMailArgs($petty);
            $this->notifyApprovers('authorize requests', $petty->department_id, new LastApprovalMail($name, $reason, $encodedId), "Petty cash request {$encodedId} has been approved and is awaiting authorization.");
        }

        return redirect()->back()->with('success', 'Request approved successfully.');
    }

    public function authorizeRequest(FinanceRequest $petty)
    {
        if ($redirect = $this->ensureUserHasSignature()) {
            return $redirect;
        }
        if (! $this->canSignRequest($petty, 'authorize')) {
            return redirect()->back()->with('error', 'You are not allowed to authorize this request at this stage.');
        }

        $this->createApproval($petty, 'authorize', 'approved');
        $petty->update(['status' => 'approved', 'approved_at' => now()]);

        if ($petty->request_type === 'Staff Requisition') {
            $this->requisitionNotifications->notifyRequester($petty, 'authorized', Auth::user());
        } else {
            [$name, $reason, $encodedId] = $this->buildRequestMailArgs($petty);
            $this->notifyApprovers('approve pettycash payments', $petty->department_id, new LastApprovalMail($name, $reason, $encodedId), "Petty cash request {$encodedId} authorized. Awaiting payment.");
        }

        return redirect()->back()->with('success', 'Request authorized successfully.');
    }

    public function c_approve(FinanceRequest $petty)
    {
        if ($petty->request_type === 'Staff Requisition') {
            return redirect()->back()->with('error', 'Staff requisitions are not paid through petty cash.');
        }
        if ($petty->authorized_at === null) {
            return redirect()->back()->with('error', 'This request must be authorized before payment.');
        }
        $alreadyPaid = $petty->approvals()->where('action', 'paid')->exists();
        if ($alreadyPaid) {
            return redirect()->back()->with('error', 'This request has already been paid.');
        }

        $latestDeposit = Deposit::latest()->first();
        if (! $latestDeposit) {
            return redirect()->back()->with('error', 'No deposit available.');
        }

        $latestDeposit->remaining -= $petty->amount;
        $latestDeposit->save();

        $log = $this->createApproval($petty, 'payment', 'paid');
        $petty->update(['status' => 'paid', 'paid_at' => $log->acted_at]);
        $petty->pettyDetails()->update(['paid_date' => optional($log->acted_at)->toDateString()]);

        [$name, $reason, $encodedId] = $this->buildRequestMailArgs($petty);
        $requester = User::find($petty->user_id);
        if ($requester) {
            $channel = $requester->notification_channel ?? 'sms';
            if (in_array($channel, ['email', 'both']) && $requester->email) {
                $this->trySendMail($requester->email, new SuccessPayment($name, $reason, $encodedId));
            }
            if (in_array($channel, ['sms', 'both']) && $requester->phone) {
                try {
                    app(NotificationService::class)->sendSms($requester->phone, "Payment done for petty cash {$encodedId}.");
                } catch (\Throwable $e) {
                    Log::error('Failed to send payment SMS', ['user_id' => $requester->id, 'error' => $e->getMessage()]);
                }
            }
        }

        return redirect()->back()->with('success', 'Payment done successfully, and the amount has been deducted from your deposit.');
    }

    public function reject(Request $request, FinanceRequest $petty)
    {
        $validated = $request->validate([
            'comment' => 'required|string',
            'action' => 'required|string|in:rejected,resubmission',
        ]);

        $this->createApproval($petty, 'system', $validated['action'], $validated['comment']);
        $petty->update(['status' => $validated['action']]);

        if ($petty->request_type === 'Staff Requisition') {
            $event = $validated['action'] === 'rejected' ? 'rejected' : 'resubmission_requested';
            $this->requisitionNotifications->notifyRequester($petty, $event, Auth::user(), $validated['comment']);

            $message = $validated['action'] === 'rejected'
                ? 'This request was rejected and feedback sent successfully.'
                : 'Request updated and feedback sent successfully.';

            return redirect()->back()->with('success', $message);
        }

        [$name, $reason, $publicId] = $this->buildRequestMailArgs($petty);
        $requester = User::find($petty->user_id);
        if ($requester) {
            $channel = $requester->notification_channel ?? 'sms';
            if ($validated['action'] === 'rejected') {
                if (in_array($channel, ['email', 'both']) && $requester->email) {
                    $this->trySendMail($requester->email, new RejectMail($name, $reason, $publicId));
                }
                if (in_array($channel, ['sms', 'both']) && $requester->phone) {
                    app(NotificationService::class)->sendSms($requester->phone, "Your petty cash {$publicId} was rejected.");
                }

                return redirect()->back()->with('success', 'This request was rejected and feedback sent successfully.');
            }

            if (in_array($channel, ['email', 'both']) && $requester->email) {
                $this->trySendMail($requester->email, new ResubmitMail($name, $reason, $publicId));
            }
            if (in_array($channel, ['sms', 'both']) && $requester->phone) {
                app(NotificationService::class)->sendSms($requester->phone, "Your petty cash {$publicId} requires resubmission.");
            }
        }

        return redirect()->back()->with('success', 'Request updated and feedback sent successfully.');
    }

    public function requisitionRequests()
    {
        $requests = FinanceRequest::with(['user', 'requisitionDetails'])
            ->where('user_id', Auth::id())
            ->where('request_type', 'Staff Requisition')
            ->orderByDesc('created_at')
            ->get();

        return view('requests.requisition.index', compact('requests'));
    }

    public function requisitionShow(FinanceRequest $petty)
    {
        abort_unless($petty->request_type === 'Staff Requisition', 404);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_unless((string) $petty->user_id === (string) Auth::id() || ($user && $user->can('view requested pettycash')), 403);

        $request = $petty->load(['user', 'department', 'requisitionDetails', 'requisitionItems', 'approvals.user']);
        $approval_logs = $request->approvals()->with('user')->orderBy('acted_at')->get();

        return view('requests.requisition.show', compact('request', 'approval_logs'));
    }

    public function requisitionEdit(FinanceRequest $petty)
    {
        abort_unless($petty->request_type === 'Staff Requisition', 404);
        abort_unless((string) $petty->user_id === (string) Auth::id(), 403);
        if (! in_array($petty->status, ['draft', 'pending', 'resubmission'])) {
            return redirect()->route('requisition.show', $petty)->with('error', 'Only draft, pending, or resubmission requisitions can be edited.');
        }

        $request = $petty->load(['requisitionDetails', 'requisitionItems']);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $planActivities = $this->requisitionPlanActivitiesForUser($user);

        return view('requests.requisition.edit', compact('request', 'planActivities'));
    }

    public function requisitionUpdate(Request $request, FinanceRequest $petty)
    {
        abort_unless($petty->request_type === 'Staff Requisition', 404);
        abort_unless((string) $petty->user_id === (string) Auth::id(), 403);
        if (! in_array($petty->status, ['draft', 'pending', 'resubmission'])) {
            return redirect()->route('requisition.show', $petty)->with('error', 'Only draft, pending, or resubmission requisitions can be updated.');
        }

        $isDraft = $petty->status === 'draft';
        $saveAsDraft = $isDraft && $this->isRequisitionDraftAction($request);
        $submitDraft = $isDraft && $this->isRequisitionSubmitAction($request);

        $rules = $saveAsDraft
            ? $this->requisitionDraftValidationRules()
            : $this->requisitionSubmitValidationRules();

        $validated = $request->validate(array_merge([
            'department_id' => ['nullable', 'string', 'short_id', 'exists:departments,id'],
            'action' => 'nullable|string|in:draft,submit',
        ], $rules));

        $items = $this->normalizeRequisitionItems($request->input('items', []));

        if (! $saveAsDraft && $items === []) {
            return redirect()->back()->withInput()->withErrors([
                'items' => 'Add at least one line item before submitting.',
            ]);
        }

        if (! empty($validated['department_id'])) {
            $petty->update(['department_id' => $validated['department_id']]);
        }

        $this->syncRequisitionDetails($petty, $validated, $items);

        if ($submitDraft) {
            $this->finalizeRequisitionSubmission($petty->fresh(['user', 'department', 'requisitionDetails']));

            return redirect()->route('requisition.index')->with('success', 'Requisition submitted successfully!');
        }

        if ($isDraft) {
            return redirect()->route('requisition.edit', $petty)->with('success', 'Draft saved.');
        }

        $petty->update(['status' => 'pending']);

        return redirect()->route('requisition.show', $petty)->with('success', 'Requisition updated successfully.');
    }

    public function requisitionApprovals()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $stage = FinanceRequest::userCanAccessStage($user, 'review') ? 'review' : $this->firstAccessibleApprovalStage($user);

        return redirect()->route('petty.list', ['stage' => $stage]);
    }

    public function downloadRequisition(FinanceRequest $petty)
    {
        if ($petty->request_type !== 'Staff Requisition') {
            abort(404);
        }
        if (! $this->isRequisitionFullySigned($petty)) {
            return redirect()->back()->with('error', 'Requisition can be downloaded only after all signatures are completed.');
        }
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (! $user || ! $user->canAny(['approve pettycash payments', 'authorize staff requisition', 'download staff requisition'])) {
            abort(403);
        }

        $request = $petty->load(['user', 'department', 'requisitionDetails', 'requisitionItems', 'approvals.user']);
        $pdf = Pdf::loadView('requests.requisition.pdf', compact('request'));

        return $pdf->download('staff-requisition-'.$request->id.'.pdf');
    }

    public function destroy(FinanceRequest $petty)
    {
        abort_unless((string) $petty->user_id === (string) Auth::id(), 403);
        if (! in_array($petty->status, ['draft', 'pending', 'resubmission', 'rejected'])) {
            return redirect()->back()->with('error', 'Only draft, pending, resubmission, or rejected requests can be deleted.');
        }
        $petty->delete();

        return redirect()->back()->with('success', 'Request deleted successfully.');
    }

    /**
     * Sub-department IDs under the user's parent department (all units in that department).
     *
     * @return list<string>
     */
    private function departmentSubDepartmentIdsForUser(?User $user): array
    {
        if (! $user?->department_id) {
            return [];
        }

        $department = Department::find($user->department_id);
        if (! $department) {
            return [];
        }

        $parentDepartment = $department->parent_id
            ? Department::find($department->parent_id)
            : $department;

        if (! $parentDepartment) {
            return [(string) $department->id];
        }

        $subDepartmentIds = $parentDepartment->children()->pluck('id')->map(fn ($id) => (string) $id)->all();

        return $subDepartmentIds !== [] ? $subDepartmentIds : [(string) $parentDepartment->id];
    }

    private function requisitionPlanActivitiesForUser(?User $user): Collection
    {
        $subDepartmentIds = $this->departmentSubDepartmentIdsForUser($user);

        if ($subDepartmentIds === []) {
            return collect();
        }

        return YearPlanActivity::query()
            ->with(['department', 'yearPlan'])
            ->whereIn('department_id', $subDepartmentIds)
            ->join('year_plans', 'year_plans.id', '=', 'year_plan_activities.year_plan_id')
            ->orderByDesc('year_plans.year')
            ->orderBy('year_plan_activities.sort_order')
            ->select('year_plan_activities.*')
            ->get()
            ->map(fn (YearPlanActivity $activity) => [
                'id' => (string) $activity->id,
                'code' => $activity->displayCode(),
                'description' => $activity->description,
                'department_id' => (string) $activity->department_id,
                'year' => $activity->yearPlan?->year,
            ]);
    }

    private function resolveDepartmentScopeIds(?string $departmentId): array
    {
        if ($departmentId === null || $departmentId === '') {
            return [];
        }
        $department = Department::find($departmentId);
        if (! $department) {
            return [$departmentId];
        }

        return $department->selfAndDescendantIds();
    }

    private function isRequisitionDraftAction(Request $request): bool
    {
        return $request->input('action') === 'draft';
    }

    private function isRequisitionSubmitAction(Request $request): bool
    {
        return $request->input('action', 'submit') === 'submit';
    }

    /**
     * @return array<string, mixed>
     */
    private function requisitionDraftValidationRules(): array
    {
        return [
            'project_program' => 'nullable|string|max:255',
            'activity_code' => 'nullable|string|max:255',
            'activity_title' => 'nullable|string|max:255',
            'budget_code' => 'nullable|string|max:255',
            'date_of_request' => 'nullable|date',
            'date_of_requirement' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
            'items' => 'nullable|array',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:255',
            'items.*.cost' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requisitionSubmitValidationRules(): array
    {
        return [
            'project_program' => 'required|string|max:255',
            'activity_code' => 'required|string|max:255',
            'activity_title' => 'required|string|max:255',
            'budget_code' => 'required|string|max:255',
            'date_of_request' => 'nullable|date',
            'date_of_requirement' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:255',
            'items.*.unit' => 'nullable|string|max:255',
            'items.*.cost' => 'required|numeric|min:0',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRequisitionItems(array $items): array
    {
        return collect($items)
            ->filter(function ($item) {
                return filled($item['description'] ?? null)
                    || filled($item['unit'] ?? null)
                    || filled($item['cost'] ?? null);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncRequisitionDetails(FinanceRequest $requestRow, array $validated, array $items): void
    {
        $amount = collect($items)->sum(fn ($item) => (float) ($item['cost'] ?? 0));

        RequisitionRequest::updateOrCreate(
            ['request_id' => $requestRow->id],
            [
                'project_program' => $validated['project_program'] ?? null,
                'activity_code' => $validated['activity_code'] ?? null,
                'activity_title' => $validated['activity_title'] ?? null,
                'budget_code' => $validated['budget_code'] ?? null,
                'date_of_request' => $validated['date_of_request'] ?? null,
                'date_of_requirement' => $validated['date_of_requirement'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'amount' => $amount,
            ]
        );

        RequisitionItem::where('request_id', $requestRow->id)->delete();
        foreach ($items as $index => $item) {
            RequisitionItem::create([
                'request_id' => $requestRow->id,
                'serial_no' => $index + 1,
                'description' => $item['description'] ?? '',
                'unit' => $item['unit'] ?? null,
                'cost' => $item['cost'] ?? 0,
            ]);
        }
    }

    private function finalizeRequisitionSubmission(FinanceRequest $requestRow): void
    {
        $requestRow->update([
            'status' => 'pending',
            'submitted_at' => $requestRow->submitted_at ?? now(),
        ]);

        $hasCreatedLog = $requestRow->approvals()
            ->where('step', 'system')
            ->where('action', 'created')
            ->exists();

        if (! $hasCreatedLog) {
            RequestApproval::create([
                'request_id' => $requestRow->id,
                'step' => 'system',
                'action' => 'created',
                'actor_id' => $requestRow->user_id,
                'acted_at' => now(),
            ]);
        }

        $this->requisitionNotifications->notifyStageApprovers(
            $requestRow->fresh(['user', 'department', 'requisitionDetails']),
            'submitted'
        );
    }
}
