<?php

namespace App\Http\Controllers;

use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryLocationController extends Controller
{
    public function index()
    {
        $locations = InventoryLocation::query()
            ->with(['parent'])
            ->withCount(['children'])
            ->orderByRaw("CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $parentLocations = InventoryLocation::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('inventory.locations.index', compact('locations', 'parentLocations'));
    }

    public function store(Request $request)
    {
        $locationId = $request->filled('location_id') ? $request->input('location_id') : null;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_locations', 'name')
                    ->where(fn ($query) => $query->where('parent_id', $request->input('parent_id')))
                    ->ignore($locationId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'parent_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
        ]);

        if ($locationId) {
            $location = InventoryLocation::query()->findOrFail($locationId);

            if ($this->createsCycle($location, $validated['parent_id'] ?? null)) {
                return redirect()->back()->withErrors([
                    'parent_id' => 'Invalid parent location selected.',
                ])->withInput();
            }

            $location->update($validated);

            return redirect()->route('inventory.locations.index')->with('success', 'Location updated successfully.');
        }

        InventoryLocation::query()->create($validated);

        return redirect()->route('inventory.locations.index')->with('success', 'Location created successfully.');
    }

    public function update(Request $request, InventoryLocation $location)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_locations', 'name')
                    ->where(fn ($query) => $query->where('parent_id', $request->input('parent_id')))
                    ->ignore($location->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'parent_id' => ['nullable', 'string', 'short_id', 'exists:inventory_locations,id'],
        ]);

        if ($this->createsCycle($location, $validated['parent_id'] ?? null)) {
            return redirect()->back()->withErrors([
                'parent_id' => 'Invalid parent location selected.',
            ])->withInput();
        }

        $location->update($validated);

        return redirect()->route('inventory.locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy(InventoryLocation $location)
    {
        if ($location->children()->exists()) {
            return redirect()->route('inventory.locations.index')->with('error', 'Location has sub-locations. Remove or reassign them first.');
        }

        $location->delete();

        return redirect()->route('inventory.locations.index')->with('success', 'Location deleted successfully.');
    }

    private function createsCycle(InventoryLocation $location, ?string $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === (string) $location->id) {
            return true;
        }

        return in_array($parentId, $location->descendantIds(), true);
    }
}
