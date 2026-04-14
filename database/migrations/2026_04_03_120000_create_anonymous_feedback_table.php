<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymous_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('category', 32);
            $table->text('body');
            $table->string('status', 32)->default('new');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymous_feedback');
    }
};
