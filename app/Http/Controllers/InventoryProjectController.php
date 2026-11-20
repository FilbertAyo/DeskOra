<?php

namespace App\Http\Controllers;

use App\Models\InventoryDonor;
use App\Models\InventoryProject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryProject::query()
            ->with('donor')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('donor', function ($donorQuery) use ($search) {
                        $donorQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $donors = InventoryDonor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Inventory/Projects/Index', [
            'projects' => $query->get(),
            'donors' => $donors,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $projectId = $request->filled('project_id') ? $request->input('project_id') : null;

        $validated = $this->validated($request, $projectId);

        if ($projectId) {
            InventoryProject::query()->findOrFail($projectId)->update($validated);

            return redirect()->route('inventory.projects.index')->with('success', 'Project updated successfully.');
        }

        InventoryProject::query()->create($validated);

        return redirect()->route('inventory.projects.index')->with('success', 'Project created successfully.');
    }

    public function update(Request $request, InventoryProject $project)
    {
        $project->update($this->validated($request, $project->id));

        return redirect()->route('inventory.projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(InventoryProject $project)
    {
        $project->delete();

        return redirect()->route('inventory.projects.index')->with('success', 'Project deleted successfully.');
    }

    private function validated(Request $request, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_projects', 'name')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'donor_id' => ['nullable', 'string', 'short_id', 'exists:inventory_donors,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (empty($validated['donor_id'])) {
            $validated['donor_id'] = null;
        }

        return $validated;
    }
}
