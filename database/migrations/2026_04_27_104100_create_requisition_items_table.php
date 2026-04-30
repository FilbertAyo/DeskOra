<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('request_id')->constrained('requests')->cascadeOnDelete();
            // Legacy reference used only for transitional backfill mapping.
            // Keep as nullable column without FK to avoid cross-schema/charset FK issues.
            $table->char('petty_id', 26)->nullable();
            $table->unsignedInteger('serial_no');
            $table->string('description');
            $table->string('unit')->nullable();
            $table->decimal('cost', 12, 2);
            $table->timestamps();

            $table->index(['request_id', 'serial_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
    }
};
