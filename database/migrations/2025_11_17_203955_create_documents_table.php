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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('period_id')->nullable();
            $table->enum('type', ['weekly_plan', 'weekly_report', 'monthly_report', 'weekly_minutes']);
            $table->string('file_path')->nullable();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // Note: period_id foreign key will be added after work_periods table is created
            
            // Indexes for better query performance
            $table->index('user_id');
            $table->index('period_id');
            $table->index('type');
            $table->index('state');
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
