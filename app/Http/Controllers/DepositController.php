<?php

namespace App\Http\Controllers;

use App\Exports\RequisitionRecordsExport;
use App\Exports\RunningBalanceExport;
use App\Models\Deposit;
use App\Models\Request as FinanceRequest;
use App\Services\CashFlowForecastService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class DepositController extends Controller
{
    public function __construct(
        private readonly CashFlowForecastService $cashFlowForecast,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $scopeIds = $user->departmentScopeIds();
        $deposits = Deposit::with('user:id,name')->orderBy('id', 'desc')
            ->whereIn('department_id', $scopeIds)
            ->get();

        // Get the latest deposit entry to access the 'remaining' value
        $latestDeposit = Deposit::orderBy('id', 'desc')
            ->whereIn('department_id', $scopeIds)
            ->first();
        $remaining = $latestDeposit ? $latestDeposit->remaining : 0;
        $cashFlowForecast = $this->cashFlowForecast->forUser($user);

        return Inertia::render('Finance/Deposits', [
            'deposits' => $deposits->map(fn (Deposit $deposit) => [
                'id' => (string) $deposit->id,
                'deposit' => (float) $deposit->deposit,
                'remaining' => (float) $deposit->remaining,
                'createdAt' => $deposit->created_at?->toIso8601String(),
                'description' => $deposit->description,
                'depositedBy' => $deposit->user?->name ?? 'Unknown',
            ]),
            'remaining' => (float) $remaining,
            'cashFlowForecast' => $cashFlowForecast,
            'formContext' => [
                'userId' => (string) $user->id,
                'departmentId' => (string) $user->department_id,
                'canDeposit' => $user->can('approve pettycash payments'),
            ],
        ]);
    }

    private function getCashflowTransactions($filterType)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $departmentIds = $user->departmentScopeIds();

        $allDeposits = Deposit::whereIn('department_id', $departmentIds)->get();
        $allPetty = FinanceRequest::with(['user', 'pettyDetails'])
            ->whereIn('department_id', $departmentIds)
            ->where('request_type', '!=', 'Staff Requisition')
            ->where('status', 'paid')
            ->get();

        $allTransactions = collect();

        foreach ($allDeposits as $deposit) {
            $allTransactions->push([
                'date' => $deposit->created_at,
                'type' => 'Deposit',
                'requested_by' => optional($deposit->department)->name,
                'deposit' => $deposit->deposit ?? 0, // Ensure it's never null
                'deduction' => 0,
            ]);
        }

        foreach ($allPetty as $petty) {
            $allTransactions->push([
                'date' => $petty->paid_date,
                'type' => 'Petty',
                'requested_by' => optional($petty->user)->name ?? 'Unknown',
                'deposit' => 0,
                'deduction' => (float) ($petty->amount ?? 0),
            ]);
        }

        $allTransactions = $allTransactions->sortBy('date')->values();
        $initialBalance = 0;
        $runningBalance = $initialBalance;

        if (in_array($filterType, ['daily', 'monthly'])) {
            $grouped = $allTransactions->groupBy(function ($tx) use ($filterType) {
                return $filterType === 'daily'
                    ? \Carbon\Carbon::parse($tx['date'])->toDateString()
                    : \Carbon\Carbon::parse($tx['date'])->format('F Y');
            });

            $groupedData = [];

            foreach ($grouped as $key => $group) {
                $dayTotal = 0;
                foreach ($group as $tx) {
                    if ($tx['type'] === 'Deposit') {
                        $runningBalance += $tx['deposit'] ?? 0;
                    } elseif ($tx['type'] === 'Petty') {
                        $runningBalance -= $tx['deduction'] ?? 0;
                        $dayTotal += $tx['deduction'] ?? 0;
                    }
                }
                $groupedData[] = [
                    'label' => $key,
                    'deduction' => $dayTotal,
                    'remaining' => $runningBalance,
                    'department' => optional(Auth::user()->department)->name,
                ];
            }

            return [
                'transactions' => $groupedData,
                'isFiltered' => true,
                'filterType' => $filterType,
            ];
        }

        // Default (no filter)
        $transactions = $allTransactions->map(function ($tx) use (&$runningBalance) {
            if ($tx['type'] === 'Deposit') {
                $runningBalance += $tx['deposit'];
            } elseif ($tx['type'] === 'Petty') {
                $runningBalance -= $tx['deduction'];
            }
            $tx['remaining'] = $runningBalance;

            return $tx;
        });

        return [
            'transactions' => $transactions,
            'isFiltered' => false,
            'filterType' => null,
        ];
    }

    public function cashflow(Request $request)
    {
        $filterType = $request->input('filter_type');
        $data = $this->getCashflowTransactions($filterType);
        $data['cashFlowForecast'] = $this->cashFlowForecast->forUser(Auth::user());

        return Inertia::render('Finance/Cashflow', [
            ...$data,
            'transactions' => collect($data['transactions'])->values(),
        ]);
    }

    public function download(Request $request)
    {
        $format = $request->get('format');
        $filterType = $request->get('filter_type');

        $data = $this->getCashflowTransactions($filterType);
        $transactions = $data['transactions'];
        $department = strtoupper(Auth::user()->department->name);

        if ($filterType === 'daily' || $filterType === 'monthly') {
            $isFiltered = true;
        } else {
            $isFiltered = false;
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('requests.finance.cashflow_pdf', [
                'transactions' => $transactions,
                'department' => $department,
                'filterType' => $filterType,
                'isFiltered' => $isFiltered, // ✅ Add this line
            ]);

            return $pdf->download('daily_running_balance_report.pdf');
        }

        if ($format === 'excel') {
            return Excel::download(
                new RunningBalanceExport(collect($transactions), $department, $isFiltered),
                'running_balance_report.xlsx'
            );
        }

        return back()->with('error', 'Invalid format requested.');
    }

    private function getRequisitionRecordTransactions(?string $filterType): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $departmentIds = $user->departmentScopeIds();

        $requisitions = FinanceRequest::with(['user', 'requisitionDetails', 'department', 'approvals.actor'])
            ->whereIn('department_id', $departmentIds)
            ->where('request_type', 'Staff Requisition')
            ->where('status', 'approved')
            ->whereHas('approvals', fn ($query) => $query->where('step', 'authorize')->where('action', 'approved'))
            ->get()
            ->sortBy(fn (FinanceRequest $request) => $request->authorized_at)
            ->values();

        $rows = $requisitions->map(function (FinanceRequest $request) {
            return [
                'date' => $request->authorized_at,
                'request_id' => $request->id,
                'reference' => $request->code,
                'requester' => optional($request->user)->name ?? 'Unknown',
                'activity' => $request->activity_title ?: $request->request_for,
                'project' => $request->project_program ?? '-',
                'budget_code' => $request->budget_code ?? '-',
                'amount' => (float) $request->amount,
                'authorized_by' => optional($request->authorized_by)->name ?? '-',
            ];
        });

        if (in_array($filterType, ['daily', 'monthly'], true)) {
            $grouped = $rows->groupBy(function (array $row) use ($filterType) {
                return $filterType === 'daily'
                    ? \Carbon\Carbon::parse($row['date'])->toDateString()
                    : \Carbon\Carbon::parse($row['date'])->format('F Y');
            });

            $cumulative = 0;
            $groupedData = [];

            foreach ($grouped as $key => $group) {
                $periodTotal = $group->sum('amount');
                $cumulative += $periodTotal;
                $groupedData[] = [
                    'label' => $key,
                    'amount' => $periodTotal,
                    'cumulative' => $cumulative,
                    'count' => $group->count(),
                ];
            }

            return [
                'transactions' => $groupedData,
                'isFiltered' => true,
                'filterType' => $filterType,
            ];
        }

        $cumulative = 0;
        $transactions = $rows->map(function (array $row) use (&$cumulative) {
            $cumulative += $row['amount'];
            $row['cumulative'] = $cumulative;

            return $row;
        });

        return [
            'transactions' => $transactions,
            'isFiltered' => false,
            'filterType' => null,
        ];
    }

    public function requisitionRecords(Request $request)
    {
        $filterType = $request->input('filter_type');
        $data = $this->getRequisitionRecordTransactions($filterType);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Inertia::render('Finance/RequisitionRecords', [
            ...$data,
            'transactions' => collect($data['transactions'])->values(),
            'canDownloadRequisition' => $user->canAny([
                'download staff requisition',
                'authorize staff requisition',
                'approve pettycash payments',
            ]),
        ]);
    }

    public function downloadRequisitionRecords(Request $request)
    {
        $format = $request->get('format');
        $filterType = $request->get('filter_type');
        $data = $this->getRequisitionRecordTransactions($filterType);
        $transactions = $data['transactions'];
        $department = strtoupper(optional(Auth::user()->department)->name ?? 'DEPARTMENT');
        $isFiltered = in_array($filterType, ['daily', 'monthly'], true);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('requests.finance.requisition-records_pdf', [
                'transactions' => $transactions,
                'department' => $department,
                'filterType' => $filterType,
                'isFiltered' => $isFiltered,
            ]);

            return $pdf->download('requisition_records_report.pdf');
        }

        if ($format === 'excel') {
            return Excel::download(
                new RequisitionRecordsExport(collect($transactions), $isFiltered),
                'requisition_records_report.xlsx'
            );
        }

        return back()->with('error', 'Invalid format requested.');
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $request->validate([
            'deposit' => ['required', 'numeric', 'min:100'],
            'user_id' => 'required|string',
            'department_id' => 'required|string',
        ]);

        $lastDeposit = Deposit::latest()->first();

        // Calculate the new remaining balance by adding the new deposit to the previous remaining balance
        $newRemaining = ($lastDeposit ? $lastDeposit->remaining : 0) + $request->deposit;

        // Save the new deposit and remaining balance
        $newRequest = Deposit::create([
            'deposit' => $request->deposit,
            'user_id' => $request->user_id,
            'department_id' => $request->department_id,
            'created_at' => $request->created_at,
            'description' => $request->description,
            'remaining' => $newRemaining,
        ]);

        return redirect()->back()->with('success', 'Fund deposited successfully with updated balance.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Deposit $deposit)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Deposit $deposit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Deposit $deposit)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deposit $deposit)
    {
        //
    }
}
