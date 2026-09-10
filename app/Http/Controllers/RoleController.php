<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return view('settings.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return view('settings.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create([
            'name' => trim((string) $validated['name']),
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->toastSuccess('Role created', $role->name.' is ready to assign.');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::query()->orderBy('name')->get();
        $selectedPermissions = $role->permissions->pluck('name')->all();
        $isSystemManaged = $this->isSystemManagedRole($role);

        return view('settings.roles.edit', compact('role', 'permissions', 'selectedPermissions', 'isSystemManaged'));
    }

    public function update(Request $request, Role $role)
    {
        if ($this->isSystemManagedRole($role)) {
            return redirect()->route('roles.index')
                ->with('error', 'The demo viewer role is system-managed and cannot be changed.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role->update([
            'name' => trim((string) $validated['name']),
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->toastSuccess('Role updated', $role->name.' was saved.');
    }

    public function destroy(Role $role)
    {
        if ($this->isSystemManagedRole($role)) {
            return redirect()->route('roles.index')
                ->with('error', 'The demo viewer role is system-managed and cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', 'Cannot delete a role that is assigned to users.');
        }

        $roleName = $role->name;
        $role->delete();

        return redirect()->route('roles.index')
            ->toastSuccess('Role deleted', $roleName.' was removed.');
    }

    private function isSystemManagedRole(Role $role): bool
    {
        return $role->name === (string) config('demo.role', 'demo_viewer');
    }
}
