<?php

namespace App\Console\Commands;

use App\Services\AiAssistService;
use Illuminate\Console\Command;
use Throwable;

class GroqStatusCommand extends Command
{
    protected $signature = 'groq:status
                            {--ping : Send a tiny test request to Groq}';

    protected $description = 'Show whether Groq AI is configured in runtime config (after .env changes, run config:clear)';

    public function handle(AiAssistService $aiAssist): int
    {
        $cached = is_file(base_path('bootstrap/cache/config.php'));
        $runtimeKey = trim((string) config('services.groq.api_key', ''));
        $envFileKey = $this->envFileValue('GROQ_API_KEY');
        $model = (string) config('services.groq.model', '');

        $this->line('Config cache: '.($cached ? 'yes (bootstrap/cache/config.php)' : 'no'));
        $this->line('GROQ_API_KEY in .env file: '.$this->keyPresence($envFileKey));
        $this->line('GROQ_API_KEY in runtime config: '.$this->keyPresence($runtimeKey));
        $this->line('GROQ_MODEL: '.($model !== '' ? $model : '(default)'));

        if ($envFileKey !== '' && $runtimeKey === '') {
            $this->newLine();
            $this->error('The .env key is set, but Laravel is still using an old cached config.');
            $this->line('On the server run:');
            $this->line('  php artisan config:clear');
            $this->line('  php artisan config:cache');

            return self::FAILURE;
        }

        if ($runtimeKey === '') {
            $this->newLine();
            $this->error('No Groq key in runtime config. Add GROQ_API_KEY to .env (free key from https://console.groq.com/keys), then config:clear.');

            return self::FAILURE;
        }

        if (! str_starts_with($runtimeKey, 'gsk_')) {
            $this->warn('Runtime key does not start with gsk_. Groq keys usually look like gsk_...');
        }

        if (! $this->option('ping')) {
            $this->newLine();
            $this->info('Runtime config has a Groq key. Polish can still fail if the key is invalid or Groq is blocked.');
            $this->line('Re-run with --ping to send a test request: php artisan groq:status --ping');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Pinging Groq...');

        try {
            $result = $aiAssist->polishPerformanceTips(['Keep submitting weekly reports on time.']);
            if (! empty($result['used_ai'])) {
                $this->info('Groq responded. AI polish is working.');

                return self::SUCCESS;
            }

            $this->error($result['message'] ?? 'Groq did not return a usable response.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function keyPresence(string $value): string
    {
        if ($value === '') {
            return 'missing';
        }

        $prefix = str_starts_with($value, 'gsk_') ? 'gsk_' : substr($value, 0, 4);

        return 'set ('.$prefix.'…, '.strlen($value).' chars)';
    }

    private function envFileValue(string $name): string
    {
        $path = base_path('.env');
        if (! is_readable($path)) {
            return '';
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            if (! str_starts_with($trimmed, $name.'=')) {
                continue;
            }

            $raw = substr($trimmed, strlen($name) + 1);
            $raw = trim($raw);
            $raw = trim($raw, '"\'');

            return $raw;
        }

        return '';
    }
}
