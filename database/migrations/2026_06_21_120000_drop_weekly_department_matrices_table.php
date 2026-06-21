<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('weekly_department_matrices');
    }

    public function down(): void
    {
        // Intentionally left empty — matrix feature was removed.
    }
};
