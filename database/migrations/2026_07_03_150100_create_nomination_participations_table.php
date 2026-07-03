<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks that a user has responded to a nomination period (nominated or skipped).
     * Intentionally records only WHO responded, never WHOM they nominated, so it can
     * power participation stats and prevent re-prompting without breaking anonymity.
     */
    public function up(): void
    {
        Schema::create('nomination_participations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('nomination_period_id')->constrained('nomination_periods')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 16);
            $table->timestamps();

            $table->unique(['nomination_period_id', 'user_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomination_participations');
    }
};
