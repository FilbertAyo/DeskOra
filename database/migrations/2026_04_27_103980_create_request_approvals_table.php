<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->constrained('requests')->cascadeOnDelete();
            $table->enum('step', ['review', 'approve', 'authorize', 'payment', 'system']);
            $table->enum('action', ['approved', 'rejected', 'resubmission', 'resubmitted', 'paid', 'created']);
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'step', 'action']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
