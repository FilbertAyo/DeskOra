<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_work_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->constrained('work_periods')->cascadeOnDelete();
            $table->date('log_date');
            $table->text('done');
            $table->text('blocker')->nullable();
            $table->text('next')->nullable();
            $table->string('action_task_uid', 80)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_id', 'log_date']);
            $table->index(['period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_work_logs');
    }
};
