<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\InventoryLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryLocationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab') === 'rooms' ? 'rooms' : 'locations';

        $sites = InventoryLocation::query()
            ->whereNull('parent_id')
            ->withCount(['children'])
            ->orderBy('name')
            ->get();

        $rooms = InventoryLocation::query()
            ->whereNotNull('parent_id')
            ->with(['parent'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/Locations/Index', [
            'tab' => $tab,
            'sites' => $sites,
            'rooms' => $rooms,
            'parentLocations' => $sites,
        ]);
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

            return redirect()
                ->route('inventory.locations.index', ['tab' => $this->tabFor($validated['parent_id'] ?? null)])
                ->with('success', filled($validated['parent_id'] ?? null) ? 'Room updated successfully.' : 'Location updated successfully.');
        }

        InventoryLocation::query()->create($validated);

        return redirect()
            ->route('inventory.locations.index', ['tab' => $this->tabFor($validated['parent_id'] ?? null)])
            ->with('success', filled($validated['parent_id'] ?? null) ? 'Room created successfully.' : 'Location created successfully.');
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

        return redirect()
            ->route('inventory.locations.index', ['tab' => $this->tabFor($validated['parent_id'] ?? null)])
            ->with('success', filled($validated['parent_id'] ?? null) ? 'Room updated successfully.' : 'Location updated successfully.');
    }

    public function destroy(InventoryLocation $location)
    {
        if ($location->children()->exists()) {
            return redirect()
                ->route('inventory.locations.index', ['tab' => 'locations'])
                ->with('error', 'This location has rooms. Remove or reassign them first.');
        }

        $tab = $location->parent_id ? 'rooms' : 'locations';
        $location->delete();

        return redirect()
            ->route('inventory.locations.index', ['tab' => $tab])
            ->with('success', $tab === 'rooms' ? 'Room deleted successfully.' : 'Location deleted successfully.');
    }

    private function tabFor(?string $parentId): string
    {
        return filled($parentId) ? 'rooms' : 'locations';
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
