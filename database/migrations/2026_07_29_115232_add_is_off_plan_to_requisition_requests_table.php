<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisition_requests', function (Blueprint $table) {
            $table->boolean('is_off_plan')->default(false)->after('budget_code');
        });
    }

    public function down(): void
    {
        Schema::table('requisition_requests', function (Blueprint $table) {
            $table->dropColumn('is_off_plan');
        });
    }
};
