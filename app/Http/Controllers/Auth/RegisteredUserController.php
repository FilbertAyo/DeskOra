<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     * Public self-registration is disabled; admins create users.
     */
    public function create(): RedirectResponse
    {
        return redirect(route('login', absolute: false));
    }

    /**
     * Handle an incoming registration request.
     * Intentionally disabled — use Admin user management instead.
     */
    public function store(Request $request): RedirectResponse
    {
        return redirect(route('login', absolute: false));
    }
}
