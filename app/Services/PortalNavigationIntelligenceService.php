<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use RuntimeException;

class PortalNavigationIntelligenceService
{
    private ?array $catalog = null;

    public function loadCatalog(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $path = (string) config('portal-navigation.path');

        if (! File::exists($path)) {
            throw new RuntimeException('Portal navigation configuration not found at '.$path);
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded) || ! isset($decoded['entries']) || ! is_array($decoded['entries'])) {
            throw new RuntimeException('Portal navigation configuration is invalid.');
        }

        $this->catalog = $decoded;

        return $this->catalog;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entriesForUser(?User $user = null): array
    {
        return array_values(array_filter(
            $this->loadCatalog()['entries'],
            fn (array $entry): bool => $this->entryVisibleToUser($entry, $user)
        ));
    }

    public function findByIntent(string $question, ?User $user = null): array
    {
        $question = strtolower(trim($question));

        if ($question === '') {
            return [];
        }

        $matches = [];

        foreach ($this->entriesForUser($user) as $entry) {
            $score = $this->scoreEntryAgainstQuestion($entry, $question);

            if ($score > 0) {
                $matches[] = ['entry' => $entry, 'score' => $score];
            }
        }

        usort($matches, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            fn (array $match): array => $match['entry'],
            $matches
        );
    }

    public function resolveUrl(array $entry): ?string
    {
        if (($entry['dynamic'] ?? false) || empty($entry['route'])) {
            return null;
        }

        if (! Route::has($entry['route'])) {
            return null;
        }

        $params = $entry['route_params'] ?? [];

        foreach ($params as $value) {
            if (is_string($value) && str_contains($value, '{')) {
                return null;
            }
        }

        $url = route($entry['route'], $params);

        if (! empty($entry['query']) && is_array($entry['query'])) {
            $url .= '?'.http_build_query($entry['query']);
        }

        return $url;
    }

    public function navigationRulesForAi(): string
    {
        return implode(' ', [
            'NAVIGATION RULES (mandatory):',
            'You must use ONLY the PORTAL NAVIGATION CATALOG below as the single source of truth for routes, menu paths, and available actions.',
            'Never invent, guess, or assume URLs, menu items, or features that are not listed in the catalog.',
            'When guiding the user, cite the menu path from the catalog and include the exact URL when one is provided.',
            'If the user asks for something that does not match any catalog entry or intent, clearly say that action or page is not available in the portal.',
            'If an entry is informational only (no URL), explain using the description without making up a link.',
            'If the user lacks permission for an entry, explain that they may not have access and suggest asking an administrator.',
            'Do not claim you can open pages for the user; give them the menu path and URL to navigate themselves.',
        ]);
    }

    public function formatCatalogForAi(?User $user = null): string
    {
        $catalog = $this->loadCatalog();
        $lines = [
            'PORTAL NAVIGATION CATALOG (version '.$catalog['version'].', updated '.$catalog['updated'].'):',
        ];

        foreach ($this->entriesForUser($user) as $entry) {
            $parts = [
                '['.$entry['id'].']',
                $entry['label'],
                'Menu: '.($entry['menu_path'] ?? 'N/A'),
            ];

            if ($url = $this->resolveUrl($entry)) {
                $parts[] = 'URL: '.$url;
            }

            if (! empty($entry['description'])) {
                $parts[] = 'About: '.$entry['description'];
            }

            if (! empty($entry['intents']) && is_array($entry['intents'])) {
                $parts[] = 'Intents: '.implode('; ', $entry['intents']);
            }

            $lines[] = '- '.implode(' | ', $parts);
        }

        return implode("\n", $lines);
    }

    public function formatPromptContext(?User $user = null): string
    {
        return $this->navigationRulesForAi()."\n\n".$this->formatCatalogForAi($user);
    }

    private function entryVisibleToUser(array $entry, ?User $user): bool
    {
        if ($user === null) {
            return empty($entry['permission'])
                && empty($entry['permissions'])
                && empty($entry['permissions_any']);
        }

        if (! empty($entry['permission']) && ! $user->can($entry['permission'])) {
            return false;
        }

        if (! empty($entry['permissions']) && is_array($entry['permissions'])) {
            foreach ($entry['permissions'] as $permission) {
                if (! $user->can($permission)) {
                    return false;
                }
            }
        }

        if (! empty($entry['permissions_any']) && is_array($entry['permissions_any'])) {
            $allowed = false;

            foreach ($entry['permissions_any'] as $permission) {
                if ($user->can($permission)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }

    private function scoreEntryAgainstQuestion(array $entry, string $question): int
    {
        $score = 0;

        if (! empty($entry['intents']) && is_array($entry['intents'])) {
            foreach ($entry['intents'] as $intent) {
                $intent = strtolower(trim((string) $intent));

                if ($intent === '') {
                    continue;
                }

                if ($question === $intent) {
                    $score += 100;
                } elseif (str_contains($question, $intent)) {
                    $score += 50 + min(strlen($intent), 30);
                } elseif (str_contains($intent, $question)) {
                    $score += 20;
                }
            }
        }

        $label = strtolower((string) ($entry['label'] ?? ''));

        if ($label !== '' && str_contains($question, $label)) {
            $score += 15;
        }

        return $score;
    }
}
