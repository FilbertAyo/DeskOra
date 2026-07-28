<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_letter_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('department_code', 16);
            $table->char('year_month', 7);
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique(['department_code', 'year_month'], 'official_letter_sequences_dept_month_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_letter_sequences');
    }
};
