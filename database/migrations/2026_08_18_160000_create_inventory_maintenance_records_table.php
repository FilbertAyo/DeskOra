<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_maintenance_records', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('record_number')->unique();
            $table->string('asset_id', 26)->index();
            $table->enum('status', ['reported', 'completed'])->default('reported')->index();
            $table->text('problem_description');
            $table->string('reported_by_user_id', 26)->index();
            $table->timestamp('reported_at');
            $table->string('previous_status')->nullable();
            $table->string('previous_condition')->nullable();
            $table->string('repair_provider')->nullable();
            $table->decimal('repair_cost', 14, 2)->nullable();
            $table->date('repaired_on')->nullable();
            $table->text('repair_notes')->nullable();
            $table->string('condition_after')->nullable();
            $table->string('completed_by_user_id', 26)->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('inventory_assets')->cascadeOnDelete();
            $table->foreign('reported_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_maintenance_records');
    }
};
