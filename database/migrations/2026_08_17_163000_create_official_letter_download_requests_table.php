<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_letter_download_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('official_letter_id')->constrained('official_letters')->cascadeOnDelete();
            $table->foreignUlid('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->text('request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['official_letter_id', 'status']);
            $table->index('requested_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_letter_download_requests');
    }
};
