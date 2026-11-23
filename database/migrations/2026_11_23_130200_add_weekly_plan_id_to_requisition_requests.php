<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_requests', function (Blueprint $table) {
            $table->foreignUlid('weekly_plan_id')
                ->nullable()
                ->after('request_id')
                ->constrained('weekly_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requisition_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_plan_id');
        });
    }
};
