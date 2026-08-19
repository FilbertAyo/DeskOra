<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_asset_assignments', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('asset_id', 26)->index();
            $table->string('assigned_to_user_id', 26)->nullable()->index();
            $table->string('assigned_to_department_id', 26)->nullable()->index();
            $table->string('assigned_by_user_id', 26)->index();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('inventory_assets')->cascadeOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_asset_transfers', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('asset_id', 26)->index();
            $table->string('from_location_id', 26)->nullable()->index();
            $table->string('to_location_id', 26)->index();
            $table->text('reason');
            $table->string('transferred_by_user_id', 26)->index();
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('inventory_assets')->cascadeOnDelete();
            $table->foreign('from_location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('to_location_id')->references('id')->on('inventory_locations')->restrictOnDelete();
            $table->foreign('transferred_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_asset_transfers');
        Schema::dropIfExists('inventory_asset_assignments');
    }
};
