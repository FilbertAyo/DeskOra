<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceRequest>
 */
class RequestFactory extends Factory
{
    protected $model = FinanceRequest::class;

    public function definition(): array
    {
        return [
            'request_no' => 'PC-'.fake()->unique()->numerify('####'),
            'request_type' => 'Petty Cash',
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'status' => 'pending',
            'submitted_at' => now(),
            'approved_at' => null,
            'paid_at' => null,
        ];
    }

    public function requisition(): static
    {
        return $this->state(fn () => [
            'request_no' => 'REQ-'.fake()->unique()->numerify('####'),
            'request_type' => 'Staff Requisition',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
