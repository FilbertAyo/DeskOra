<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plan_budget_reviews', function (Blueprint $table) {
            $table->enum('approval_stage', ['pending', 'reviewed', 'approved', 'authorized', 'rejected'])
                ->default('pending')
                ->after('decision');
            $table->foreignUlid('approved_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignUlid('authorized_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable()->after('authorized_by');
            $table->index('approval_stage');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plan_budget_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn(['approval_stage', 'approved_at', 'authorized_at']);
        });
    }
};

