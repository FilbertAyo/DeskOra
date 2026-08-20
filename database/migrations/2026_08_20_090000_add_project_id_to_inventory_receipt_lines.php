<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_receipt_lines', function (Blueprint $table) {
            $table->string('project_id', 26)->nullable()->after('category_id')->index();
            $table->foreign('project_id')->references('id')->on('inventory_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
