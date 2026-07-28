<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SystemSettingService
{
    private const CACHE_PREFIX = 'system_setting:';

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

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            array_filter([
                'group' => $group,
                'type' => $type,
                'value' => $storedValue,
                'label' => $label,
                'description' => $description,
            ], fn ($item) => $item !== null)
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
    }

    public function formsLogoPublicPath(): string
    {
        $path = $this->get(SystemSetting::KEY_FORMS_LOGO);

        if (is_string($path) && $path !== '' && $this->logoFileExists($path)) {
            return $path;
        }

        return 'assets/images/logo.png';
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

        return public_path('assets/images/logo.png');
    }

    public function storeFormsLogo(UploadedFile $file): SystemSetting
    {
        $this->ensureDefaults();

        $existing = $this->get(SystemSetting::KEY_FORMS_LOGO);
        if (is_string($existing) && str_starts_with($existing, 'storage/')) {
            $relative = substr($existing, strlen('storage/'));
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }

        $stored = $file->store('system/forms', 'public');
        $publicPath = 'storage/'.$stored;

        return $this->set(
            SystemSetting::KEY_FORMS_LOGO,
            $publicPath,
            SystemSetting::GROUP_FORMS,
            SystemSetting::TYPE_FILE,
            'Forms logo',
            'Logo shown on printable forms such as the Fund Requisition Form.'
        );
    }

    public function clearFormsLogo(): SystemSetting
    {
        $existing = $this->get(SystemSetting::KEY_FORMS_LOGO);
        if (is_string($existing) && str_starts_with($existing, 'storage/')) {
            $relative = substr($existing, strlen('storage/'));
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }

        return $this->set(
            SystemSetting::KEY_FORMS_LOGO,
            null,
            SystemSetting::GROUP_FORMS,
            SystemSetting::TYPE_FILE,
            'Forms logo',
            'Logo shown on printable forms such as the Fund Requisition Form.'
        );
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
