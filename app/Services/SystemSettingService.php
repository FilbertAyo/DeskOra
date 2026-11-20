<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SystemSettingService
{
    private const CACHE_PREFIX = 'system_setting:';

    private const DEFAULT_LOGO = 'assets/images/logo.png';

    private const DEFAULT_ICON = 'assets/images/logo-icon.png';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX.$key, function () use ($key, $default) {
            $setting = SystemSetting::query()->where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return $this->castValue($setting);
        });
    }

    public function getSetting(string $key): ?SystemSetting
    {
        return SystemSetting::query()->where('key', $key)->first();
    }

    /**
     * @return array<string, SystemSetting>
     */
    public function allInGroup(string $group): array
    {
        return SystemSetting::query()
            ->where('group', $group)
            ->orderBy('key')
            ->get()
            ->keyBy('key')
            ->all();
    }

    public function set(
        string $key,
        mixed $value,
        string $group = SystemSetting::GROUP_GENERAL,
        string $type = SystemSetting::TYPE_STRING,
        ?string $label = null,
        ?string $description = null,
    ): SystemSetting {
        $storedValue = $this->serializeValue($value, $type);

        $attributes = array_filter([
            'group' => $group,
            'type' => $type,
            'label' => $label,
            'description' => $description,
        ], fn ($item) => $item !== null);

        $attributes['value'] = $storedValue;

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            $attributes
        );

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    public function ensureDefaults(): void
    {
        SystemSetting::query()->firstOrCreate(
            ['key' => SystemSetting::KEY_FORMS_LOGO],
            [
                'group' => SystemSetting::GROUP_FORMS,
                'type' => SystemSetting::TYPE_FILE,
                'value' => null,
                'label' => 'Forms logo',
                'description' => 'Logo shown on printable forms such as the Fund Requisition Form.',
            ]
        );

        SystemSetting::query()->firstOrCreate(
            ['key' => SystemSetting::KEY_APP_NAME],
            [
                'group' => SystemSetting::GROUP_BRANDING,
                'type' => SystemSetting::TYPE_STRING,
                'value' => config('app.name', 'Adilisha Portal'),
                'label' => 'System name',
                'description' => 'Display name shown across the portal sidebar, footer, and login.',
            ]
        );

        SystemSetting::query()->firstOrCreate(
            ['key' => SystemSetting::KEY_LOGO],
            [
                'group' => SystemSetting::GROUP_BRANDING,
                'type' => SystemSetting::TYPE_FILE,
                'value' => null,
                'label' => 'System photo',
                'description' => 'Organization photo or logo shown on login and branded surfaces.',
            ]
        );

        SystemSetting::query()->firstOrCreate(
            ['key' => SystemSetting::KEY_ICON],
            [
                'group' => SystemSetting::GROUP_BRANDING,
                'type' => SystemSetting::TYPE_FILE,
                'value' => null,
                'label' => 'System icon',
                'description' => 'Square icon used in the sidebar, browser tab, and authentication pages.',
            ]
        );

        SystemSetting::query()->firstOrCreate(
            ['key' => SystemSetting::KEY_PRIMARY_COLOR],
            [
                'group' => SystemSetting::GROUP_BRANDING,
                'type' => SystemSetting::TYPE_STRING,
                'value' => SystemSetting::DEFAULT_PRIMARY_COLOR,
                'label' => 'Primary color',
                'description' => 'Accent color used for buttons, links, and highlights.',
            ]
        );
    }

    /**
     * @return array{
     *     appName: string,
     *     productName: string,
     *     logo: string,
     *     logoIcon: string,
     *     primaryColor: string,
     *     usingDefaultLogo: bool,
     *     usingDefaultIcon: bool,
     *     logoPath: string,
     *     iconPath: string
     * }
     */
    public function branding(): array
    {
        $productName = (string) config('branding.product_name', 'DeskOra');
        $fallbackName = (string) config('app.name', 'Adilisha Portal');

        if (! Schema::hasTable('system_settings')) {
            return [
                'appName' => $fallbackName,
                'productName' => $productName,
                'logo' => asset(self::DEFAULT_LOGO),
                'logoIcon' => asset(self::DEFAULT_ICON),
                'primaryColor' => SystemSetting::DEFAULT_PRIMARY_COLOR,
                'usingDefaultLogo' => true,
                'usingDefaultIcon' => true,
                'logoPath' => self::DEFAULT_LOGO,
                'iconPath' => self::DEFAULT_ICON,
            ];
        }

        $appName = trim((string) ($this->get(SystemSetting::KEY_APP_NAME) ?? ''));
        if ($appName === '') {
            $appName = $fallbackName;
        }

        $primary = trim((string) ($this->get(SystemSetting::KEY_PRIMARY_COLOR) ?? ''));
        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $primary)) {
            $primary = SystemSetting::DEFAULT_PRIMARY_COLOR;
        }

        $logoPath = $this->publicAssetPath(SystemSetting::KEY_LOGO, self::DEFAULT_LOGO);
        $iconPath = $this->publicAssetPath(SystemSetting::KEY_ICON, self::DEFAULT_ICON);

        return [
            'appName' => $appName,
            'productName' => $productName,
            'logo' => asset($logoPath),
            'logoIcon' => asset($iconPath),
            'primaryColor' => $primary,
            'usingDefaultLogo' => $this->isUsingDefault(SystemSetting::KEY_LOGO),
            'usingDefaultIcon' => $this->isUsingDefault(SystemSetting::KEY_ICON),
            'logoPath' => $logoPath,
            'iconPath' => $iconPath,
        ];
    }

    public function formsLogoPublicPath(): string
    {
        return $this->publicAssetPath(SystemSetting::KEY_FORMS_LOGO, self::DEFAULT_LOGO);
    }

    public function formsLogoAbsolutePath(): string
    {
        $path = $this->formsLogoPublicPath();

        if (str_starts_with($path, 'storage/')) {
            $relative = substr($path, strlen('storage/'));
            $absolute = Storage::disk('public')->path($relative);
            if (is_file($absolute)) {
                return $absolute;
            }
        }

        $publicPath = public_path($path);
        if (is_file($publicPath)) {
            return $publicPath;
        }

        return public_path(self::DEFAULT_LOGO);
    }

    public function storeFormsLogo(UploadedFile $file): SystemSetting
    {
        return $this->storeUploadedFile(
            SystemSetting::KEY_FORMS_LOGO,
            $file,
            'system/forms',
            SystemSetting::GROUP_FORMS,
            'Forms logo',
            'Logo shown on printable forms such as the Fund Requisition Form.'
        );
    }

    public function clearFormsLogo(): SystemSetting
    {
        return $this->clearUploadedFile(
            SystemSetting::KEY_FORMS_LOGO,
            SystemSetting::GROUP_FORMS,
            'Forms logo',
            'Logo shown on printable forms such as the Fund Requisition Form.'
        );
    }

    public function storeBrandingLogo(UploadedFile $file): SystemSetting
    {
        return $this->storeUploadedFile(
            SystemSetting::KEY_LOGO,
            $file,
            'system/branding',
            SystemSetting::GROUP_BRANDING,
            'System photo',
            'Organization photo or logo shown on login and branded surfaces.'
        );
    }

    public function clearBrandingLogo(): SystemSetting
    {
        return $this->clearUploadedFile(
            SystemSetting::KEY_LOGO,
            SystemSetting::GROUP_BRANDING,
            'System photo',
            'Organization photo or logo shown on login and branded surfaces.'
        );
    }

    public function storeBrandingIcon(UploadedFile $file): SystemSetting
    {
        return $this->storeUploadedFile(
            SystemSetting::KEY_ICON,
            $file,
            'system/branding',
            SystemSetting::GROUP_BRANDING,
            'System icon',
            'Square icon used in the sidebar, browser tab, and authentication pages.'
        );
    }

    public function clearBrandingIcon(): SystemSetting
    {
        return $this->clearUploadedFile(
            SystemSetting::KEY_ICON,
            SystemSetting::GROUP_BRANDING,
            'System icon',
            'Square icon used in the sidebar, browser tab, and authentication pages.'
        );
    }

    public function setAppName(string $name): SystemSetting
    {
        return $this->set(
            SystemSetting::KEY_APP_NAME,
            $name,
            SystemSetting::GROUP_BRANDING,
            SystemSetting::TYPE_STRING,
            'System name',
            'Display name shown across the portal sidebar, footer, and login.'
        );
    }

    public function setPrimaryColor(string $color): SystemSetting
    {
        return $this->set(
            SystemSetting::KEY_PRIMARY_COLOR,
            strtolower($color),
            SystemSetting::GROUP_BRANDING,
            SystemSetting::TYPE_STRING,
            'Primary color',
            'Accent color used for buttons, links, and highlights.'
        );
    }

    private function storeUploadedFile(
        string $key,
        UploadedFile $file,
        string $directory,
        string $group,
        string $label,
        string $description,
    ): SystemSetting {
        $this->ensureDefaults();
        $this->deleteStoredFile($this->get($key));

        $stored = $file->store($directory, 'public');
        $publicPath = 'storage/'.$stored;

        return $this->set(
            $key,
            $publicPath,
            $group,
            SystemSetting::TYPE_FILE,
            $label,
            $description
        );
    }

    private function clearUploadedFile(
        string $key,
        string $group,
        string $label,
        string $description,
    ): SystemSetting {
        $this->deleteStoredFile($this->get($key));

        return $this->set(
            $key,
            null,
            $group,
            SystemSetting::TYPE_FILE,
            $label,
            $description
        );
    }

    private function deleteStoredFile(mixed $existing): void
    {
        if (! is_string($existing) || ! str_starts_with($existing, 'storage/')) {
            return;
        }

        $relative = substr($existing, strlen('storage/'));
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }

    private function publicAssetPath(string $key, string $default): string
    {
        $path = $this->get($key);

        if (is_string($path) && $path !== '' && $this->logoFileExists($path)) {
            return $path;
        }

        return $default;
    }

    private function isUsingDefault(string $key): bool
    {
        $path = $this->get($key);

        return $path === null || $path === '';
    }

    private function logoFileExists(string $path): bool
    {
        if (str_starts_with($path, 'storage/')) {
            return Storage::disk('public')->exists(substr($path, strlen('storage/')));
        }

        return is_file(public_path($path));
    }

    private function castValue(SystemSetting $setting): mixed
    {
        return match ($setting->type) {
            SystemSetting::TYPE_BOOLEAN => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            SystemSetting::TYPE_JSON => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    private function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            SystemSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            SystemSetting::TYPE_JSON => is_string($value) ? $value : json_encode($value),
            default => (string) $value,
        };
    }
}
