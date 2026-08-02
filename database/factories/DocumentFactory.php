<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_id' => WorkPeriod::factory(),
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => fake()->sentence(3),
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ];
    }

    public function weeklyPlan(): static
    {
        return $this->state(fn () => [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Weekly Plan',
        ]);
    }

    public function weeklyReport(): static
    {
        return $this->state(fn () => [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'state' => Document::STATE_SUBMITTED,
        ]);
    }
}
