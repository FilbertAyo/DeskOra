<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replenishments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('from');
            $table->date('to');
            $table->decimal('total_amount', 12, 2);
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replenishments');
    }
};
