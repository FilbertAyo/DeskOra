<?php

namespace App\Http\Controllers;

use App\Mail\NotificationMail;
use App\Models\Agenda;
use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Vinkla\Hashids\Facades\Hashids;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = User::all();

        return view('settings.users.index', compact('user'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $departments = Department::where('status', 'active')->get();
        $permissions = Permission::all();
        $roles = Role::all();

        return view('settings.users.create', compact('departments', 'permissions', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone' => ['required', 'regex:/^0[76][0-9]{8}$/'],
            'department_id' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $randomNo = Str::random(6);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($randomNo),
            'department_id' => $request->department_id,
            'phone' => $request->phone,
            'status' => 'active',
            'file' => null, //Default value if no file is uploaded
        ]);

        // Assign permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->input('permissions', []));
        }

        event(new Registered($user));
        // Send notification email
        $name = $user->name;
        $email = $user->email;
        $password = $randomNo;

        Mail::to($email)->send(new NotificationMail($name, $email, $password));

        return redirect()->back()->with('success', 'New user registered successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show($hashid)
    {
        $id = Hashids::decode($hashid);
        $user = User::findOrFail($id[0]);
        $permissions = Permission::all();

        return view('settings.users.view', compact('user', 'permissions'));
    }

    public function assignPermissions(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Get submitted permissions or empty array if none checked
        $submittedPermissions = $request->input('permissions', []);

        // Sync the user's permissions (this will add and remove as needed)
        $user->syncPermissions($submittedPermissions);

        return redirect()->back()->with('success', 'Permissions updated successfully.');
    }

    public function edit($hashid)
    {
        $id = Hashids::decode($hashid);

        $user = User::findOrFail($id[0]);
        $departments = Department::where('status', 'active')->get();
        $permissions = Permission::all();
        $roles = Role::all();
        return view('settings.users.edit', compact('user', 'departments', 'permissions', 'roles'));
    }
    public function update(Request $request, $id)
    {

        $user = User::findOrFail($id);
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['required', 'regex:/^0[76][0-9]{8}$/'],
            'department_id' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department_id' => $request->department_id,
        ]);

        // Update user permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->input('permissions', []));
        }

        return redirect()->back()->with('success', 'User updated successfully');
    }


    public function destroy(string $id)
    {
        $user = User::find($id);

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

    public function activate(string $id)
    {
        $user = User::find($id);

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
}
