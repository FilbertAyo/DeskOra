<?php

use App\Database\DocumentsUnionView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy monolithic documents table used during migration/cutover.
     * Replaced by the documents SQL view after domain tables are populated.
     */
    public function up(): void
    {
        if (Schema::hasTable('documents') && ! DocumentsUnionView::isView()) {
            return;
        }

        // Post-cutover production already uses the documents view — keep it.
        if (DocumentsUnionView::isView() && Schema::hasTable('weekly_plans')) {
            return;
        }

        // migrate:fresh drops tables but leaves views behind; remove stale view first.
        if (DocumentsUnionView::isView()) {
            DB::statement('DROP VIEW IF EXISTS documents');
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->enum('type', ['weekly_plan', 'weekly_report', 'monthly_report', 'action_log']);
            $table->string('file_path')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();
            $table->longText('content')->nullable();
            $table->text('main_target')->nullable();
            $table->boolean('target_accomplished')->nullable();
            $table->text('target_explanation')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('review_rejection_reason')->nullable();
            $table->enum('budget_review_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->foreignUlid('budget_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('budget_reviewed_at')->nullable();
            $table->decimal('budget_provided_amount', 14, 2)->nullable();
            $table->text('budget_review_note')->nullable();
            $table->json('budget_review_items')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('state');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'state']);
            $table->index(['month', 'year']);
            $table->index('budget_review_status');
            $table->index('budget_reviewed_by');
        });
    }

    public function down(): void
    {
        if (DocumentsUnionView::isView()) {
            return;
        }

        Schema::dropIfExists('documents');
    }
};
