<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = Department::whereNull('parent_id')
            ->with(['head'])
            ->withCount(['users', 'children'])
            ->orderBy('name')
            ->get();
        $parentDepartments = Department::orderBy('name')->get(['id', 'name', 'parent_id']);
        $departmentHeads = \App\Models\User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return Inertia::render('Settings/Departments/Index', [
            'departments' => $departments,
            'parentDepartments' => $parentDepartments,
            'departmentHeads' => $departmentHeads,
            'storeUrl' => route('department.store'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $departmentId = $request->filled('department_id') ? $request->department_id : null;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(function ($query) use ($request) {
                    return $query->where('parent_id', $request->input('parent_id'));
                })->ignore($departmentId),
            ],
            'status' => 'required|in:active,inactive',
            'parent_id' => 'nullable|string|short_id|exists:departments,id',
            'head_user_id' => 'nullable|string|short_id|exists:users,id',
            'plan_section_number' => 'nullable|integer|min:1|max:999',
        ]);

        if (empty($validated['parent_id'])) {
            $validated['plan_section_number'] = null;
        }

        if ($departmentId) {
            $department = Department::findOrFail($departmentId);
            if ($this->createsCycle($department, $validated['parent_id'] ?? null)) {
                return redirect()->back()->withErrors([
                    'parent_id' => 'Invalid parent department selected.',
                ])->withInput();
            }
            $department->update($validated);
            $message = 'Department updated successfully';
        } else {
            Department::create($validated);
            $message = 'Department created successfully';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department)
    {
        $department->load([
            'parent',
            'children' => function ($query) {
                $query->withCount(['users', 'children'])->orderBy('name');
            },
            'head',
        ]);
        $department->loadCount(['users', 'children']);
        $users = $department->users()->latest()->get();
        $departmentHeads = \App\Models\User::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);

        return Inertia::render('Settings/Departments/Show', [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'status' => $department->status,
                'users_count' => $department->users_count,
                'children_count' => $department->children_count,
                'parent' => $department->parent?->only(['id', 'name']),
                'head' => $department->head?->only(['id', 'name']),
                'children' => $department->children->map(fn ($child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'users_count' => $child->users_count,
                    'children_count' => $child->children_count,
                ])->values(),
            ],
            'users' => $users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ])->values(),
            'departmentHeads' => $departmentHeads,
            'indexUrl' => route('departments.index'),
            'updateUrl' => route('departments.update', $department),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(function ($query) use ($request) {
                    return $query->where('parent_id', $request->input('parent_id'));
                })->ignore($department->id),
            ],
            'status' => 'required|in:active,inactive',
            'parent_id' => 'nullable|string|short_id|exists:departments,id',
            'head_user_id' => 'nullable|string|short_id|exists:users,id',
            'plan_section_number' => 'nullable|integer|min:1|max:999',
        ]);

        if (empty($validated['parent_id'])) {
            $validated['plan_section_number'] = null;
        }

        if ($this->createsCycle($department, $validated['parent_id'] ?? null)) {
            return redirect()->back()->withErrors([
                'parent_id' => 'Invalid parent department selected.',
            ])->withInput();
        }

        $department->update($validated);

        return redirect()->back()->with('success', 'Department updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department)
    {
        if ($department->children()->exists()) {
            return redirect()->back()->with('error', 'Department has sub-departments. Remove or reassign them first.');
        }

        if ($department->users()->exists()) {
            return redirect()->back()->with('error', 'Department has assigned users. Reassign users before deletion.');
        }

        $department->delete();

        return redirect()->back()->with('success', 'Department deleted successfully');
    }

    private function createsCycle(Department $department, ?string $parentId): bool
    {
        if ($parentId === null) {
            return false;
        }

        if ($parentId === (string) $department->id) {
            return true;
        }

        return in_array($parentId, $department->descendantIds(), true);
    }
}
