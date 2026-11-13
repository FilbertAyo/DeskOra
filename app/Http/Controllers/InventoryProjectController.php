<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

use App\Models\InventoryDonor;
use App\Models\InventoryProject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryProjectController extends Controller
{
    public function index()
    {
        $projects = InventoryProject::query()
            ->with('donor')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();

        $donors = InventoryDonor::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Inventory/Projects/Index', compact('projects', 'donors'));
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
