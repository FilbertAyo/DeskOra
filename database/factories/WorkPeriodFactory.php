<?php

namespace Database\Factories;

use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPeriod>
 */
class WorkPeriodFactory extends Factory
{
    protected $model = WorkPeriod::class;

    public function definition(): array
    {
        $start = Carbon::parse('2026-04-13')->startOfWeek(Carbon::MONDAY);

        return [
            'year' => (int) $start->year,
            'week_number' => (int) $start->isoWeek(),
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'plan_deadline' => $start->copy()->next(Carbon::SATURDAY)->toDateString(),
            'report_deadline' => $start->copy()->next(Carbon::SATURDAY)->toDateString(),
            'status' => WorkPeriod::STATUS_OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => WorkPeriod::STATUS_CLOSED]);
    }

    public function forWeek(Carbon $weekStart): static
    {
        $start = $weekStart->copy()->startOfWeek(Carbon::MONDAY);

        return $this->state(fn () => [
            'year' => (int) $start->year,
            'week_number' => (int) $start->isoWeek(),
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'plan_deadline' => $start->copy()->next(Carbon::SATURDAY)->toDateString(),
            'report_deadline' => $start->copy()->next(Carbon::SATURDAY)->toDateString(),
        ]);
    }
}
