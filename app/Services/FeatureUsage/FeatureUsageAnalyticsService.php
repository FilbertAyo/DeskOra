<?php

namespace App\Services\FeatureUsage;

use App\Models\FeatureUsageEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeatureUsageAnalyticsService
{
    public function __construct(
        protected FeatureUsageRecorder $recorder,
    ) {}

    /**
     * @return array{
     *     active_users: int,
     *     total_interactions: int,
     *     modules: Collection<int, array<string, mixed>>,
     *     features: Collection<int, array<string, mixed>>,
     *     chart: array{labels: list<string>, series: list<int>}
     * }
     */
    public function summarize(
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $module = null,
        ?string $eventType = null,
        ?string $userId = null,
    ): array {
        $activeUsers = User::query()
            ->where('status', 'active')
            ->count();

        $base = FeatureUsageEvent::query()
            ->whereDate('created_at', '>=', $from->toDateString())
            ->whereDate('created_at', '<=', $to->toDateString());

        if (filled($module)) {
            $base->where('module', $module);
        }

        if (filled($eventType)) {
            $base->where('event_type', $eventType);
        }

        if (filled($userId)) {
            $base->where('user_id', $userId);
        }

        $totalInteractions = (clone $base)->count();

        $moduleRows = (clone $base)
            ->select([
                'module',
                DB::raw('COUNT(*) as interactions'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
            ])
            ->groupBy('module')
            ->orderByDesc('unique_users')
            ->orderByDesc('interactions')
            ->get()
            ->map(fn ($row) => $this->enrichRow(
                label: (string) $row->module,
                key: (string) $row->module,
                interactions: (int) $row->interactions,
                uniqueUsers: (int) $row->unique_users,
                activeUsers: $activeUsers,
                totalInteractions: $totalInteractions,
            ));

        $featureRows = (clone $base)
            ->select([
                'feature_key',
                'module',
                'event_type',
                DB::raw('COUNT(*) as interactions'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
            ])
            ->groupBy('feature_key', 'module', 'event_type')
            ->orderByDesc('unique_users')
            ->orderByDesc('interactions')
            ->get()
            ->map(fn ($row) => $this->enrichRow(
                label: $this->recorder->featureLabel((string) $row->feature_key),
                key: (string) $row->feature_key,
                interactions: (int) $row->interactions,
                uniqueUsers: (int) $row->unique_users,
                activeUsers: $activeUsers,
                totalInteractions: $totalInteractions,
                extras: [
                    'module' => (string) $row->module,
                    'event_type' => (string) $row->event_type,
                ],
            ));

        $chartModules = $moduleRows->take(8);

        return [
            'active_users' => $activeUsers,
            'total_interactions' => $totalInteractions,
            'modules' => $moduleRows,
            'features' => $featureRows,
            'chart' => [
                'labels' => $chartModules->pluck('label')->values()->all(),
                'series' => $chartModules->pluck('share')->values()->all(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    protected function enrichRow(
        string $label,
        string $key,
        int $interactions,
        int $uniqueUsers,
        int $activeUsers,
        int $totalInteractions,
        array $extras = [],
    ): array {
        $adoption = $activeUsers > 0
            ? round(($uniqueUsers / $activeUsers) * 100, 1)
            : 0.0;

        $share = $totalInteractions > 0
            ? round(($interactions / $totalInteractions) * 100, 1)
            : 0.0;

        return array_merge([
            'label' => $label,
            'key' => $key,
            'interactions' => $interactions,
            'unique_users' => $uniqueUsers,
            'adoption' => $adoption,
            'share' => $share,
        ], $extras);
    }

    /**
     * @return Collection<int, string>
     */
    public function availableModules(): Collection
    {
        return FeatureUsageEvent::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module');
    }
}
