<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public const SECTIONS = ['identity', 'branding', 'appearance', 'forms'];

    public function __construct(
        private readonly SystemSettingService $settings,
    ) {}

    public function edit(Request $request): Response
    {
        $this->settings->ensureDefaults();

        $section = $this->resolveSection($request->query('section'));
        $branding = $this->settings->branding();
        $formsLogoPath = $this->settings->formsLogoPublicPath();

        return Inertia::render('Settings/System/Edit', [
            'section' => $section,
            'sections' => [
                ['id' => 'identity', 'label' => 'Identity', 'description' => 'System name shown across the portal'],
                ['id' => 'branding', 'label' => 'Logos & media', 'description' => 'Photo, icon, and mark assets'],
                ['id' => 'appearance', 'label' => 'Appearance', 'description' => 'Primary color and look'],
                ['id' => 'forms', 'label' => 'Forms & downloads', 'description' => 'Logo on printable exports'],
            ],
            'identity' => [
                'appName' => $branding['appName'],
            ],
            'brandingAssets' => [
                'logoUrl' => $branding['logo'],
                'iconUrl' => $branding['logoIcon'],
                'usingDefaultLogo' => $branding['usingDefaultLogo'],
                'usingDefaultIcon' => $branding['usingDefaultIcon'],
            ],
            'appearance' => [
                'primaryColor' => $branding['primaryColor'],
                'defaultPrimaryColor' => SystemSetting::DEFAULT_PRIMARY_COLOR,
            ],
            'forms' => [
                'formsLogoPath' => $formsLogoPath,
                'formsLogoUrl' => asset($formsLogoPath),
                'usingDefaultLogo' => $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === null
                    || $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === '',
            ],
            'updateUrl' => route('settings.system.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $this->resolveSection($request->input('section'));

        return match ($section) {
            'identity' => $this->updateIdentity($request),
            'branding' => $this->updateBranding($request),
            'appearance' => $this->updateAppearance($request),
            'forms' => $this->updateForms($request),
        };
    }

    private function updateIdentity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
        ]);

        $this->settings->setAppName(trim($validated['app_name']));

        return $this->redirectToSection('identity', 'System name updated.');
    }

    private function updateBranding(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
            'icon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_icon' => ['nullable', 'boolean'],
        ]);

        $changed = false;

        if ($request->boolean('remove_logo')) {
            $this->settings->clearBrandingLogo();
            $changed = true;
        } elseif ($request->hasFile('logo')) {
            $this->settings->storeBrandingLogo($request->file('logo'));
            $changed = true;
        }

        if ($request->boolean('remove_icon')) {
            $this->settings->clearBrandingIcon();
            $changed = true;
        } elseif ($request->hasFile('icon')) {
            $this->settings->storeBrandingIcon($request->file('icon'));
            $changed = true;
        }

        return $this->redirectToSection(
            'branding',
            $changed ? 'Branding assets updated.' : 'No changes were made.'
        );
    }

    private function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->settings->setPrimaryColor($validated['primary_color']);

        return $this->redirectToSection('appearance', 'Appearance updated.');
    }

    private function updateForms(Request $request): RedirectResponse
    {
        $request->validate([
            'forms_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_forms_logo' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_forms_logo')) {
            $this->settings->clearFormsLogo();

            return $this->redirectToSection('forms', 'Forms logo reset to the default logo.');
        }

        if ($request->hasFile('forms_logo')) {
            $this->settings->storeFormsLogo($request->file('forms_logo'));

            return $this->redirectToSection('forms', 'Forms logo updated successfully.');
        }

        return $this->redirectToSection('forms', 'No changes were made.');
    }

    private function redirectToSection(string $section, string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.system.edit', ['section' => $section])
            ->with('success', $message);
    }

    private function resolveSection(mixed $section): string
    {
        $value = is_string($section) ? $section : 'identity';

        return in_array($value, self::SECTIONS, true) ? $value : 'identity';
    }
}
