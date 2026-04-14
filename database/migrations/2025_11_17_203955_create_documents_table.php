<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->enum('type', ['weekly_plan', 'weekly_report', 'monthly_report', 'action_log']);
            $table->string('file_path')->nullable();
            $table->string('attachment')->nullable();
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('content')->nullable();
            $table->text('main_target')->nullable();
            $table->boolean('target_accomplished')->nullable();
            $table->text('target_explanation')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('review_rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('period_id');
            $table->index('type');
            $table->index('state');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'state']);
            $table->index(['month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
