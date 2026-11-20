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
    public const SECTIONS = ['brand', 'logos'];

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
                [
                    'id' => 'brand',
                    'label' => 'Brand',
                    'description' => 'System name and primary color',
                ],
                [
                    'id' => 'logos',
                    'label' => 'Logos',
                    'description' => 'Portal icon, photo, and forms logo',
                ],
            ],
            'brand' => [
                'appName' => $branding['appName'],
                'primaryColor' => $branding['primaryColor'],
                'defaultPrimaryColor' => SystemSetting::DEFAULT_PRIMARY_COLOR,
            ],
            'logos' => [
                'logoUrl' => $branding['logo'],
                'iconUrl' => $branding['logoIcon'],
                'usingDefaultLogo' => $branding['usingDefaultLogo'],
                'usingDefaultIcon' => $branding['usingDefaultIcon'],
                'formsLogoUrl' => asset($formsLogoPath),
                'usingDefaultFormsLogo' => $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === null
                    || $this->settings->get(SystemSetting::KEY_FORMS_LOGO) === '',
            ],
            'updateUrl' => route('settings.system.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $this->resolveSection($request->input('section'));

        return match ($section) {
            'brand' => $this->updateBrand($request),
            'logos' => $this->updateLogos($request),
        };
    }

    private function updateBrand(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'app_name' => ['required', 'string', 'max:120'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $this->settings->setAppName(trim($validated['app_name']));
        $this->settings->setPrimaryColor($validated['primary_color']);

        return $this->redirectToSection('brand', 'Brand settings updated.');
    }

    private function updateLogos(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:4096'],
            'icon' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'forms_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_icon' => ['nullable', 'boolean'],
            'remove_forms_logo' => ['nullable', 'boolean'],
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

        if ($request->boolean('remove_forms_logo')) {
            $this->settings->clearFormsLogo();
            $changed = true;
        } elseif ($request->hasFile('forms_logo')) {
            $this->settings->storeFormsLogo($request->file('forms_logo'));
            $changed = true;
        }

        return $this->redirectToSection(
            'logos',
            $changed ? 'Logo settings updated.' : 'No changes were made.'
        );
    }

    private function redirectToSection(string $section, string $message): RedirectResponse
    {
        return redirect()
            ->route('settings.system.edit', ['section' => $section])
            ->with('success', $message);
    }

    private function resolveSection(mixed $section): string
    {
        $value = is_string($section) ? $section : 'brand';

        // Map legacy section query values from the previous four-tab layout.
        $legacy = match ($value) {
            'identity', 'appearance' => 'brand',
            'branding', 'forms' => 'logos',
            default => $value,
        };

        return in_array($legacy, self::SECTIONS, true) ? $legacy : 'brand';
    }
}
