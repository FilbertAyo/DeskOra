<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anonymous nomination content. There is intentionally NO nominator column here,
     * so a nominee/reason can never be linked back to the person who submitted it.
     */
    public function up(): void
    {
        Schema::create('nominations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('nomination_period_id')->constrained('nomination_periods')->cascadeOnDelete();
            $table->foreignUlid('nominee_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['nomination_period_id', 'nominee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominations');
    }
};
