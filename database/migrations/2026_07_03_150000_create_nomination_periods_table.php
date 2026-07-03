<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomination_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->year('year');
            $table->unsignedTinyInteger('month');
            // dateTime (not timestamp) avoids MySQL's implicit-default rule that
            // rejects a second NOT NULL TIMESTAMP column under strict sql_mode.
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->timestamps();

            $table->unique(['year', 'month']);
            $table->index('status');
            $table->index('opens_at');
            $table->index('closes_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomination_periods');
    }
};
