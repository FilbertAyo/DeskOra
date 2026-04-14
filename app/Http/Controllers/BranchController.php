<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $branches = Branch::withCount('users')->latest()->get();

        return view('settings.branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $branchId = $request->filled('branch_id') ? $request->branch_id : null;

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'region' => 'required|string|max:255',
            'location_url' => 'required|url|max:500',
            'status' => 'required|in:active,inactive',
        ]);

        if ($branchId) {
            $branch = Branch::findOrFail($branchId);
            $branch->update($validatedData);
            $message = 'Branch updated successfully';
        } else {
            Branch::create($validatedData);
            $message = 'Branch created successfully';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        $branch->loadCount('users');

        return view('settings.branches.view', compact('branch'));
    }

    public function update(Request $request, Branch $branch)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'region' => 'required|string|max:255',
            'location_url' => 'required|url|max:500',
            'status' => 'required|in:active,inactive',
        ]);

        $branch->update($validatedData);

        return redirect()->back()->with('success', 'Branch updated successfully');
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->back()->with('success', 'Branch deleted successfully');
    }
}
