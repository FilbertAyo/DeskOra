<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_disposal_requests', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('request_number')->unique();
            $table->string('asset_id', 26)->index();
            $table->text('reason');
            $table->enum('method', ['donated', 'sold', 'destroyed', 'recycled', 'other'])->default('other');
            $table->enum('status', ['requested', 'approved', 'rejected'])->default('requested')->index();
            $table->string('requested_by_user_id', 26)->index();
            $table->string('reviewed_by_user_id', 26)->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('disposed_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('inventory_assets')->restrictOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_disposal_requests');
    }
};
