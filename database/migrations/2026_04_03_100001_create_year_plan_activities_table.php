<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('year_plan_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('year_plan_id')->constrained('year_plans')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->text('description');
            $table->decimal('frequency', 12, 2)->default(0);
            $table->decimal('unit_count', 12, 2)->default(0);
            $table->decimal('unit_value_tzs', 18, 2)->default(0);
            $table->decimal('total_cost_tzs', 18, 2)->default(0);
            $table->json('planned_months')->nullable();
            $table->timestamps();

            $table->unique(['year_plan_id', 'department_id', 'sort_order'], 'ypa_plan_dept_sort_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_plan_activities');
    }
};
