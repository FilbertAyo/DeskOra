<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Main target for weekly plans
            $table->text('main_target')->nullable()->after('content');
            
            // Accomplishment status for weekly reports
            $table->boolean('target_accomplished')->nullable()->after('main_target');
            
            // Explanation when target is not accomplished
            $table->text('target_explanation')->nullable()->after('target_accomplished');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['main_target', 'target_accomplished', 'target_explanation']);
        });
    }
};
