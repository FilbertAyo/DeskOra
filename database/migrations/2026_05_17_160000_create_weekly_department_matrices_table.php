<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_department_matrices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('period_id')->constrained('work_periods')->cascadeOnDelete();
            $table->foreignUlid('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('overall_budget', 18, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('entries');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['period_id', 'department_id'], 'wdm_period_department_unique');
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_department_matrices');
    }
};
