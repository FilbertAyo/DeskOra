<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->text('objective_reflection_progress')->nullable()->after('main_target');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_plans', function (Blueprint $table) {
            $table->dropColumn(['objective_reflection_progress']);
        });
    }
};
