<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_matrices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('period_id')->constrained('work_periods')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['period_id', 'department_id'], 'weekly_matrices_period_department_unique');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('weekly_matrix_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_matrix_id')->constrained('weekly_matrices')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('key_activity');
            $table->text('output')->nullable();
            $table->text('outcome')->nullable();
            $table->text('impact')->nullable();
            $table->text('indicators')->nullable();
            $table->text('baseline')->nullable();
            $table->text('target')->nullable();
            $table->text('means_of_verification')->nullable();
            $table->timestamps();

            $table->index(['weekly_matrix_id', 'sort_order'], 'weekly_matrix_entries_matrix_sort_index');
        });

        Schema::create('weekly_matrix_entry_year_plan_activity', function (Blueprint $table) {
            $table->foreignUlid('weekly_matrix_entry_id')
                ->constrained('weekly_matrix_entries', 'id', 'wm_entry_ypa_entry_fk')
                ->cascadeOnDelete();
            $table->foreignUlid('year_plan_activity_id')
                ->constrained('year_plan_activities', 'id', 'wm_entry_ypa_activity_fk')
                ->cascadeOnDelete();

            $table->primary(
                ['weekly_matrix_entry_id', 'year_plan_activity_id'],
                'wm_entry_ypa_primary'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_matrix_entry_year_plan_activity');
        Schema::dropIfExists('weekly_matrix_entries');
        Schema::dropIfExists('weekly_matrices');
    }
};
