<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_verification_sessions', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('session_number')->unique();
            $table->string('name');
            $table->string('location_id', 26)->nullable()->index();
            $table->string('department_id', 26)->nullable()->index();
            $table->enum('status', ['open', 'completed'])->default('open')->index();
            $table->text('notes')->nullable();
            $table->string('started_by_user_id', 26)->index();
            $table->timestamp('started_at');
            $table->string('completed_by_user_id', 26)->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('started_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_verification_results', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('session_id', 26)->index();
            $table->string('asset_id', 26)->index();
            $table->enum('result', ['pending', 'found', 'missing', 'damaged', 'location_changed', 'person_changed'])->default('pending')->index();
            $table->string('recorded_location_id', 26)->nullable();
            $table->string('recorded_responsible_user_id', 26)->nullable();
            $table->text('notes')->nullable();
            $table->string('recorded_by_user_id', 26)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'asset_id'], 'inv_vfy_session_asset_unique');
            $table->foreign('session_id', 'inv_vfy_session_fk')->references('id')->on('inventory_verification_sessions')->cascadeOnDelete();
            $table->foreign('asset_id', 'inv_vfy_asset_fk')->references('id')->on('inventory_assets')->restrictOnDelete();
            $table->foreign('recorded_location_id', 'inv_vfy_loc_fk')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('recorded_responsible_user_id', 'inv_vfy_resp_user_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('recorded_by_user_id', 'inv_vfy_recorded_by_fk')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_verification_results');
        Schema::dropIfExists('inventory_verification_sessions');
    }
};
