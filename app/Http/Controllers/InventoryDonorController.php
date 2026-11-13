<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\InventoryDonor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryDonorController extends Controller
{
    public function index()
    {
        $donors = InventoryDonor::query()
            ->withCount('projects')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Donors/Index', compact('donors'));
    }

    public function store(Request $request)
    {
        $donorId = $request->filled('donor_id') ? $request->input('donor_id') : null;

        $validated = $this->validated($request, $donorId);

        if ($donorId) {
            InventoryDonor::query()->findOrFail($donorId)->update($validated);

            return redirect()->route('inventory.donors.index')->with('success', 'Donor updated successfully.');
        }

        InventoryDonor::query()->create($validated);

        return redirect()->route('inventory.donors.index')->with('success', 'Donor created successfully.');
    }

    public function update(Request $request, InventoryDonor $donor)
    {
        $donor->update($this->validated($request, $donor->id));

        return redirect()->route('inventory.donors.index')->with('success', 'Donor updated successfully.');
    }

    public function destroy(InventoryDonor $donor)
    {
        if ($donor->projects()->exists()) {
            return redirect()->route('inventory.donors.index')->with('error', 'Donor has linked projects. Remove or reassign them first.');
        }

        $donor->delete();

        return redirect()->route('inventory.donors.index')->with('success', 'Donor deleted successfully.');
    }

    private function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_donors', 'name')->ignore($ignoreId),
            ],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
