<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'signature' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = Auth::user();

        if (! $user) {
            return redirect()->back()->withErrors(['error' => 'Something went wrong. Please try again later.']);
        }

        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imagePath = $image->store('profile', 'public'); // saved in storage/app/public/profile
            $user->file = 'storage/'.$imagePath; // accessible via public/storage/profile/...
        }

        if ($request->hasFile('signature')) {
            $signature = $request->file('signature');
            $signaturePath = $signature->store('signatures', 'public'); // saved in storage/app/public/signatures
            $user->signature = 'storage/'.$signaturePath; // accessible via public/storage/signatures/...
        }

        $user->save();

        if ($request->hasFile('signature')) {
            return redirect()->back()->with('status', 'signature-updated');
        }

        return redirect()->back()->with('success', 'Your profile updated successfully.');
    }
}
