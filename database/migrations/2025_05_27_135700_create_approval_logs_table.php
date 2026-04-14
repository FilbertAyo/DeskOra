<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('petty_id')->nullable()->constrained('petties')->cascadeOnDelete();
            $table->foreignUlid('replenishment_id')->nullable()->constrained('replenishments')->nullOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['approved', 'rejected', 'resubmission', 'resubmitted', 'paid', 'created']);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
