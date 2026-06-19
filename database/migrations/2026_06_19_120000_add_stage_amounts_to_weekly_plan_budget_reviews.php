<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plan_budget_reviews', function (Blueprint $table) {
            $table->decimal('review_provided_amount', 14, 2)->nullable()->after('provided_amount');
            $table->decimal('approve_provided_amount', 14, 2)->nullable()->after('review_provided_amount');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plan_budget_reviews', function (Blueprint $table) {
            $table->dropColumn(['review_provided_amount', 'approve_provided_amount']);
        });
    }
};
