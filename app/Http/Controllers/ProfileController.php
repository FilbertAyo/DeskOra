<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->loadMissing('department', 'roles');

        $nameParts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        $initials = collect(array_slice($nameParts, 0, 2))
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        $activities = Activity::query()
            ->causedBy($user)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'title' => $activity->description ?: ucfirst((string) $activity->event),
                'description' => filled($activity->log_name)
                    ? ucfirst(str_replace(['-', '_'], ' ', (string) $activity->log_name))
                    : null,
                'time' => $activity->created_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'notification_channel' => $user->notification_channel ?? 'sms',
                'avatar' => asset($user->file ?? 'assets/images/profile.png'),
                'initials' => $initials !== '' ? $initials : 'U',
                'signature' => $user->signature ? asset($user->signature) : null,
                'department' => $user->department?->name,
                'role' => $user->getRoleNames()->first() ?: 'Staff',
                'status' => ucfirst((string) ($user->status ?: 'active')),
                'member_since' => optional($user->created_at)->format('M d, Y'),
                'joined_label' => optional($user->created_at)->format('F Y'),
            ],
            'activities' => $activities,
            'tab' => $request->string('tab')->toString() ?: 'profile',
            'urls' => [
                'update' => route('profile.update'),
                'image' => route('profile.image'),
                'password' => route('password.update'),
            ],
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
