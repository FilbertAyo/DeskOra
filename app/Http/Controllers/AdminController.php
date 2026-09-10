<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminUserRequest;
use App\Http\Requests\UpdateAdminUserRequest;
use App\Mail\NotificationMail;
use App\Models\Department;
use App\Models\User;
use App\Services\WorkflowUserNotificationDefaults;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $user = User::with(['department.head', 'subDepartments', 'roles'])->latest()->paginate(20);

        return view('settings.users.index', compact('user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $departments = $this->hierarchicalDepartments();
        $topDepartments = $this->topDepartmentsWithChildren();
        $roles = Role::all();

        return view('settings.users.create', compact('departments', 'topDepartments', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdminUserRequest $request)
    {
        $validated = $request->validated();

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

        $roleNames = $validated['roles'] ?? [];
        $user->syncRoles($roleNames);
        $this->logUserRoleAssignment($user, $roleNames);
        WorkflowUserNotificationDefaults::apply($user->fresh(), $roleNames);

        $this->syncDepartmentHeadAssignment(
            $user,
            $validated['department_id'] ?? null,
            $request->boolean('is_department_head')
        );

        event(new Registered($user));
        // Send notification email
        Mail::to($user->email)->send(new NotificationMail($user->name, $user->email));

        return redirect()->back()
            ->toastSuccess('User registered', $user->name.' can now sign in.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $admin)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $user = $admin->load(['department', 'subDepartments', 'roles', 'permissions']);

        return view('settings.users.view', compact('user'));
    }

    public function edit(Request $request, User $admin)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $user = $admin->load(['department', 'subDepartments']);
        $departments = $this->hierarchicalDepartments();
        $topDepartments = $this->topDepartmentsWithChildren();
        $roles = Role::all();

        return view('settings.users.edit', compact('user', 'departments', 'topDepartments', 'roles'));
    }

    public function update(UpdateAdminUserRequest $request, User $admin)
    {
        $user = $admin;
        $oldDepartmentId = $user->department_id;
        $validated = $request->validated();

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

        $previousRoles = $user->getRoleNames()->values()->all();
        $roleNames = $validated['roles'] ?? [];
        $user->syncRoles($roleNames);
        $this->logUserRoleAssignment($user, $roleNames, $previousRoles);
        WorkflowUserNotificationDefaults::apply($user->fresh(), $roleNames);

        $this->syncDepartmentHeadAssignment(
            $user,
            $validated['department_id'] ?? null,
            $request->boolean('is_department_head'),
            $oldDepartmentId
        );

        return redirect()->back()
            ->toastSuccess('User updated', $user->name.' was saved.');
    }

    public function updateNotificationChannel(Request $request, User $user)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $validated = $request->validate([
            'notification_channel' => ['required', Rule::in(['sms', 'email', 'both'])],
        ]);

        $user->update([
            'notification_channel' => $validated['notification_channel'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Notification channel updated.');
    }

    public function updateSignature(Request $request, User $admin)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

        $request->validate([
            'signature' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        $signaturePath = $request->file('signature')->store('signatures', 'public');

        $admin->update([
            'signature' => 'storage/'.$signaturePath,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Signature uploaded successfully.');
    }

    public function destroy(Request $request, User $admin)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

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

    public function activate(Request $request, User $user)
    {
        abort_unless($request->user()?->can('users management settings'), 403);

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

    /**
     * @param  list<string>  $roleNames
     * @param  list<string>|null  $previousRoles
     */
    private function logUserRoleAssignment(User $user, array $roleNames, ?array $previousRoles = null): void
    {
        $previous = $previousRoles ?? [];
        sort($previous);
        $next = array_values($roleNames);
        sort($next);

        if ($previous === $next) {
            return;
        }

        activity('roles')
            ->causedBy(auth()->user())
            ->performedOn($user)
            ->event($previous === [] ? 'assigned' : 'updated')
            ->withProperties([
                'old' => ['roles' => $previous],
                'attributes' => ['roles' => $next],
            ])
            ->log($previous === []
                ? 'Roles assigned: '.($next === [] ? '(none)' : implode(', ', $next))
                : 'Roles updated for '.$user->name);
    }
}
