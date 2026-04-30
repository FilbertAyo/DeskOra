<?php

namespace App\Http\Controllers;

use App\Mail\NotificationMail;
use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = User::with(['department.head', 'subDepartments', 'roles'])->latest()->get();

        return view('settings.users.index', compact('user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = $this->hierarchicalDepartments();
        $topDepartments = $this->topDepartmentsWithChildren();
        $permissions = Permission::all();
        $roles = Role::all();

        return view('settings.users.create', compact('departments', 'topDepartments', 'permissions', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['required', 'regex:/^0[76][0-9]{8}$/'],
            'department_id' => ['nullable', 'string', 'ulid', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNull('parent_id'))],
            'sub_department_ids' => ['nullable', 'array'],
            'sub_department_ids.*' => ['string', 'ulid', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNotNull('parent_id'))],
            'is_department_head' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $subDepartmentIds = array_values(array_unique($validated['sub_department_ids'] ?? []));

        if (! empty($subDepartmentIds) && empty($validated['department_id'])) {
            return redirect()->back()->withErrors([
                'department_id' => 'Select a department before choosing sub-departments.',
            ])->withInput();
        }

        if (! empty($validated['department_id']) && empty($subDepartmentIds)) {
            return redirect()->back()->withErrors([
                'sub_department_ids' => 'At least one sub-department is required for this department.',
            ])->withInput();
        }

        if (! empty($subDepartmentIds) && ! $this->subDepartmentsBelongToDepartment($subDepartmentIds, (string) $validated['department_id'])) {
            return redirect()->back()->withErrors([
                'sub_department_ids' => 'Sub-departments must belong to the selected department.',
            ])->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'title' => $validated['title'] ?? null,
            'email' => $request->email,
            'password' => $validated['password'],
            'department_id' => $validated['department_id'] ?? null,
            'phone' => $request->phone,
            'status' => 'active',
            'file' => null, // Default value if no file is uploaded
        ]);

        $user->subDepartments()->sync($subDepartmentIds);

        // Assign permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->input('permissions', []));
        }

        $this->syncDepartmentHeadAssignment(
            $user,
            $validated['department_id'] ?? null,
            $request->boolean('is_department_head')
        );

        event(new Registered($user));
        // Send notification email
        Mail::to($user->email)->send(new NotificationMail($user->name, $user->email));

        return redirect()->back()->with('success', 'New user registered successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $admin)
    {
        $user = $admin->load(['department', 'subDepartments']);
        $permissions = Permission::all();

        return view('settings.users.view', compact('user', 'permissions'));
    }

    public function assignPermissions(Request $request, User $user)
    {

        // Get submitted permissions or empty array if none checked
        $submittedPermissions = $request->input('permissions', []);

        // Sync the user's permissions (this will add and remove as needed)
        $user->syncPermissions($submittedPermissions);

        activity('users')
            ->performedOn($user)
            ->withProperties(['permissions' => array_values($submittedPermissions)])
            ->log('User permissions updated');

        return redirect()->back()->with('success', 'Permissions updated successfully.');
    }

    public function edit(User $admin)
    {
        $user = $admin->load(['department', 'subDepartments']);
        $departments = $this->hierarchicalDepartments();
        $topDepartments = $this->topDepartmentsWithChildren();
        $permissions = Permission::all();
        $roles = Role::all();

        return view('settings.users.edit', compact('user', 'departments', 'topDepartments', 'permissions', 'roles'));
    }

    public function update(Request $request, User $admin)
    {
        $user = $admin;
        $oldDepartmentId = $user->department_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['required', 'regex:/^0[76][0-9]{8}$/'],
            'department_id' => ['nullable', 'string', 'ulid', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNull('parent_id'))],
            'sub_department_ids' => ['nullable', 'array'],
            'sub_department_ids.*' => ['string', 'ulid', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('status', 'active')->whereNotNull('parent_id'))],
            'is_department_head' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $subDepartmentIds = array_values(array_unique($validated['sub_department_ids'] ?? []));

        if (! empty($subDepartmentIds) && empty($validated['department_id'])) {
            return redirect()->back()->withErrors([
                'department_id' => 'Select a department before choosing sub-departments.',
            ])->withInput();
        }

        if (! empty($validated['department_id']) && empty($subDepartmentIds)) {
            return redirect()->back()->withErrors([
                'sub_department_ids' => 'At least one sub-department is required for this department.',
            ])->withInput();
        }

        if (! empty($subDepartmentIds) && ! empty($validated['department_id']) && ! $this->subDepartmentsBelongToDepartment($subDepartmentIds, (string) $validated['department_id'])) {
            return redirect()->back()->withErrors([
                'sub_department_ids' => 'Sub-departments must belong to the selected department.',
            ])->withInput();
        }

        $user->update([
            'name' => $request->name,
            'title' => $validated['title'] ?? null,
            'email' => $request->email,
            'phone' => $request->phone,
            'department_id' => $validated['department_id'] ?? null,
        ]);

        $user->subDepartments()->sync($subDepartmentIds);

        // Update user permissions if provided
        if ($request->has('permissions')) {
            $synced = $request->input('permissions', []);
            $user->syncPermissions($synced);

            activity('users')
                ->performedOn($user)
                ->withProperties(['permissions' => array_values($synced)])
                ->log('User permissions updated');
        }

        $this->syncDepartmentHeadAssignment(
            $user,
            $validated['department_id'] ?? null,
            $request->boolean('is_department_head'),
            $oldDepartmentId
        );

        return redirect()->back()->with('success', 'User updated successfully');
    }

    public function destroy(User $admin)
    {
        $user = $admin;

        if ($user) {
            // Update the user's status to 'inactive'
            $user->status = 'inactive';
            $user->save();

            return redirect()->back()->with('success', 'User status updated to inactive successfully');
        } else {
            // Redirect back with error message if user not found
            return redirect()->back()->with('error', 'User not found');
        }
    }

    public function activate(User $user)
    {

        if ($user) {
            // Update the user's status to 'inactive'
            $user->status = 'active';
            $user->save();

            return redirect()->back()->with('success', 'User status updated to active successfully');
        } else {
            // Redirect back with error message if user not found
            return redirect()->back()->with('error', 'User not found');
        }
    }

    private function hierarchicalDepartments()
    {
        $all = Department::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'status']);

        $grouped = $all->groupBy('parent_id');
        $ordered = collect();

        $appendChildren = function ($parentId, $depth) use (&$appendChildren, $grouped, $ordered) {
            foreach ($grouped->get($parentId, collect()) as $department) {
                $department->display_name = str_repeat('- ', $depth).$department->name;
                $ordered->push($department);
                $appendChildren($department->id, $depth + 1);
            }
        };

        $appendChildren(null, 0);

        return $ordered;
    }

    private function syncDepartmentHeadAssignment(User $user, ?string $departmentId, bool $isDepartmentHead, ?string $oldDepartmentId = null): void
    {
        if ($oldDepartmentId !== null && $oldDepartmentId !== $departmentId) {
            Department::where('id', $oldDepartmentId)
                ->where('head_user_id', $user->id)
                ->update(['head_user_id' => null]);
        }

        if ($isDepartmentHead && filled($departmentId)) {
            Department::where('head_user_id', $user->id)
                ->where('id', '!=', $departmentId)
                ->update(['head_user_id' => null]);

            Department::where('id', $departmentId)
                ->update(['head_user_id' => $user->id]);
        } elseif (filled($departmentId)) {
            Department::where('id', $departmentId)
                ->where('head_user_id', $user->id)
                ->update(['head_user_id' => null]);
        }
    }

    private function topDepartmentsWithChildren()
    {
        return Department::where('status', 'active')
            ->whereNull('parent_id')
            ->with(['children' => function ($query) {
                $query->where('status', 'active')->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function subDepartmentsBelongToDepartment(array $subDepartmentIds, string $departmentId): bool
    {
        return Department::whereIn('id', $subDepartmentIds)
            ->where('parent_id', $departmentId)
            ->count() === count($subDepartmentIds);
    }
}
