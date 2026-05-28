<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_user_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignUlid('weekly_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
            $table->unique(['weekly_plan_id', 'user_id'], 'document_user_shares_weekly_plan_user_unique');
            $table->unique(['weekly_report_id', 'user_id'], 'document_user_shares_weekly_report_user_unique');
            $table->index('weekly_plan_id', 'document_user_shares_weekly_plan_id_index');
            $table->index('weekly_report_id', 'document_user_shares_weekly_report_id_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_user_shares');
    }
};
