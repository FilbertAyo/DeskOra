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
            $table->string('attachment')->nullable()->after('file_path');
            $table->integer('month')->nullable()->after('attachment');
            $table->integer('year')->nullable()->after('month');
            
            // Add index for month and year for better query performance on monthly reports
            $table->index(['month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['month', 'year']);
            $table->dropColumn(['attachment', 'month', 'year']);
        });
    }
};
