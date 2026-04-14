<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use App\Models\Petty;
use App\Models\Replenishment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NumberToWords\NumberToWords;

class ReplenishmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $replenishments = Replenishment::orderBy('created_at', 'desc')
            ->get();

        return view('pettycash.replenishments.index', compact('replenishments'));
    }

    public function pettycash(Request $request)
    {
        // Start with a base query for paid status
        $query = Petty::where('status', 'paid');

        if ($request->filled('from') && $request->filled('to')) {
            $from = Carbon::parse($request->from)->startOfDay();
            $to = Carbon::parse($request->to)->endOfDay();

            // Use 'paid_date' for filtering since only 'paid' records are fetched
            $query->whereBetween('paid_date', [$from, $to]);
        }

        $petties = $query->get();

        return view('pettycash.replenishments.list', compact('petties'));
    }

    public function create(Request $request)
    {
        $from = $request->from;
        $to = $request->to;

        // Check overlap with existing replenishments
        $overlap = Replenishment::where('status', '!=', 'rejected')->where(function ($query) use ($from, $to) {
            $query->whereBetween('from', [$from, $to])
                ->orWhereBetween('to', [$from, $to])
                ->orWhere(function ($q) use ($from, $to) {
                    $q->where('from', '<=', $from)->where('to', '>=', $to);
                });
        })->exists();

        if ($overlap) {
            return redirect()->back()->with('error', 'This range overlaps with an existing replenishment. Please cross-check the dates on your replenishment list and try again.');
        }

        // Only sum paid petties without replenishment_id
        $fromInclusive = Carbon::parse($from)->startOfDay();
        $toInclusive = Carbon::parse($to)->endOfDay();

        $totalAmount = Petty::where('status', 'paid')
            ->whereBetween('paid_date', [$fromInclusive, $toInclusive])
            ->whereNull('replenishment_id')
            ->sum('amount');

        return view('pettycash.replenishments.create', compact('from', 'to', 'totalAmount'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Enhanced validation with custom messages
        $request->validate([
            'from' => 'required|date|before_or_equal:to',
            'to' => 'required|date|after_or_equal:from',
            'total_amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|min:10|max:1000',
        ], [
            'from.required' => 'Start date is required.',
            'from.before_or_equal' => 'Start date must be before or equal to end date.',
            'to.required' => 'End date is required.',
            'to.after_or_equal' => 'End date must be after or equal to start date.',
            'total_amount.required' => 'Total amount is required.',
            'total_amount.min' => 'Total amount must be greater than 0.',
            'description.required' => 'Description is required.',
            'description.min' => 'Description must be at least 10 characters long.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ]);

        // Check if there are any paid petties in the date range
        $fromInclusive = Carbon::parse($request->from)->startOfDay();
        $toInclusive = Carbon::parse($request->to)->endOfDay();

        $availablePetties = Petty::where('status', 'paid')
            ->whereBetween('paid_date', [$fromInclusive, $toInclusive])
            ->whereNull('replenishment_id')
            ->count();

        if ($availablePetties == 0) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No available petty cash records found in the selected date range. Please select a different date range or check if there are paid petty cash records.');
        }

        // Verify the calculated amount matches the provided amount
        $calculatedAmount = Petty::where('status', 'paid')
            ->whereBetween('paid_date', [$fromInclusive, $toInclusive])
            ->whereNull('replenishment_id')
            ->sum('amount');

        if (abs($calculatedAmount - $request->total_amount) > 0.01) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'The total amount does not match the calculated amount from available petty cash records. Please refresh the page and try again.');
        }

        // Final overlap check with better error message
        $overlap = Replenishment::where('status', '!=', 'rejected')->where(function ($query) use ($request) {
            $query->whereBetween('from', [$request->from, $request->to])
                ->orWhereBetween('to', [$request->from, $request->to])
                ->orWhere(function ($q) use ($request) {
                    $q->where('from', '<=', $request->from)->where('to', '>=', $request->to);
                });
        })->first();

        if ($overlap) {
            return redirect()->back()
                ->withInput()
                ->with('error', "This date range overlaps with an existing replenishment (ID: {$overlap->id}) from {$overlap->from} to {$overlap->to}. Please select a different date range.");
        }

        try {
            $replenishment = null;

            DB::transaction(function () use ($request, $fromInclusive, $toInclusive, &$replenishment) {
                $replenishment = Replenishment::create([
                    'from' => $request->from,
                    'to' => $request->to,
                    'total_amount' => $request->total_amount,
                    'description' => $request->description,
                    'status' => 'pending',
                ]);

                // Create approval log for the initiator
                ApprovalLog::create([
                    'replenishment_id' => $replenishment->id,
                    'user_id' => Auth::user()->id,
                    'action' => 'created',
                ]);

                // Link petty cash records to this replenishment
                $updatedCount = Petty::where('status', 'paid')
                    ->whereBetween('paid_date', [$fromInclusive, $toInclusive])
                    ->whereNull('replenishment_id')
                    ->update(['replenishment_id' => $replenishment->id]);

                // Verify the update was successful
                if ($updatedCount == 0) {
                    throw new \Exception('Failed to link petty cash records to replenishment.');
                }
            });

            return redirect()->route('replenishment.index')
                ->with('success', "Replenishment created successfully! {$availablePetties} petty cash records have been linked to this replenishment.")
                ->with('replenishment_id', $replenishment ? $replenishment->id : null);

        } catch (\Exception $e) {
            Log::error('Replenishment creation failed: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create replenishment. Please try again or contact support if the problem persists.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Replenishment $replenishment)
    {

        if ($replenishment->status === 'rejected') {
            $petties = Petty::whereDate('paid_date', '>=', $replenishment->from)
                ->whereDate('paid_date', '<=', $replenishment->to)
                ->get();
        } else {
            $petties = Petty::where('replenishment_id', $replenishment->id)->get();
        }

        $numberToWords = new NumberToWords;
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $amountWords = $numberTransformer->toWords($replenishment->total_amount);
        $amountWords = ucwords($amountWords);
        $amountInWords = 'TZS '.$amountWords;

        // Get the initiator (created action)
        $initiator = ApprovalLog::where('replenishment_id', $replenishment->id)
            ->where('action', 'created')
            ->with('user')
            ->first();

        // Get approval logs (approved actions)
        $approvalLogs = ApprovalLog::where('replenishment_id', $replenishment->id)
            ->where('action', 'approved')
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $verifier = $approvalLogs->get(0); // First approver (verifier)
        $approver = $approvalLogs->get(1); // Second approver

        return view('pettycash.replenishments.view', compact('amountInWords', 'replenishment', 'petties', 'initiator', 'verifier', 'approver'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Replenishment $replenishment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Replenishment $replenishment)
    {
        //
    }

    public function firstApprove(Replenishment $replenishment)
    {
        $replenishment->status = 'processing';
        $replenishment->save();

        ApprovalLog::create([
            'replenishment_id' => $replenishment->id,
            'user_id' => Auth::id(), // cleaner
            'action' => 'approved',
        ]);

        return redirect()->back()->with('success', 'Replenishment first approved successfully.');
    }

    public function lastApprove(Replenishment $replenishment)
    {
        $replenishment->status = 'approved';
        $replenishment->save();

        ApprovalLog::create([
            'replenishment_id' => $replenishment->id,
            'user_id' => Auth::id(),
            'action' => 'approved',
        ]);

        return redirect()->back()->with('success', 'Replenishment approved successfully.');
    }

    public function destroy(Replenishment $replenishment)
    {
        // Mark the replenishment as rejected
        $replenishment->status = 'rejected';
        $replenishment->save();

        // Unlink all petties associated with this replenishment
        Petty::where('replenishment_id', $replenishment->id)
            ->update(['replenishment_id' => null]);

        ApprovalLog::create([
            'replenishment_id' => $replenishment->id,
            'user_id' => Auth::user()->id,
            'action' => 'rejected',
        ]);

        return redirect()->back()->with('success', 'Replenishment rejected and unlinked from all petty cash records.');
    }

    public function downloadPDF(Replenishment $replenishment)
    {

        if ($replenishment->status === 'rejected') {
            $petties = Petty::whereDate('paid_date', '>=', $replenishment->from)
                ->whereDate('paid_date', '<=', $replenishment->to)
                ->get();
        } else {
            $petties = Petty::where('replenishment_id', $replenishment->id)->get();
        }

        $numberToWords = new NumberToWords;
        $numberTransformer = $numberToWords->getNumberTransformer('en');
        $amountWords = $numberTransformer->toWords($replenishment->total_amount);
        $amountWords = ucwords($amountWords);
        $amountInWords = 'TZS '.$amountWords;

        $approvalLogs = ApprovalLog::where('replenishment_id', $replenishment->id)
            ->where('action', 'approved')
            ->orderBy('created_at')
            ->get();

        $initiator = $approvalLogs->get(0) ?? null;
        $verifier = $approvalLogs->get(1) ?? null;
        $approver = $approvalLogs->get(2) ?? null;

        $pdf = Pdf::loadView('pettycash.replenishments.pdf', compact(
            'amountInWords',
            'replenishment',
            'petties',
            'initiator',
            'verifier',
            'approver'
        ));

        return $pdf->download('replenishment_'.$replenishment->id.'.pdf');
    }
}
