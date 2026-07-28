<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $settings,
    ) {}

    public function edit(): View
    {
        $this->settings->ensureDefaults();

        $formsLogoPath = $this->settings->formsLogoPublicPath();
        $formsLogoUrl = asset($formsLogoPath);
        $usingDefaultLogo = $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === null
            || $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === '';

        return view('settings.system.edit', compact(
            'formsLogoPath',
            'formsLogoUrl',
            'usingDefaultLogo',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'forms_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_forms_logo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_forms_logo')) {
            $this->settings->clearFormsLogo();

            return redirect()
                ->route('settings.system.edit')
                ->with('success', 'Forms logo reset to the default logo.');
        }

        if ($request->hasFile('forms_logo')) {
            $this->settings->storeFormsLogo($request->file('forms_logo'));

            return redirect()
                ->route('settings.system.edit')
                ->with('success', 'Forms logo updated successfully.');
        }

        return redirect()
            ->route('settings.system.edit')
            ->with('success', 'No changes were made.');
    }
}
