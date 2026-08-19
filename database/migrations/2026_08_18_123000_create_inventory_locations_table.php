<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->string('parent_id', 26)->nullable()->index();
            $table->timestamps();

            $table->foreign('parent_id')
                ->references('id')
                ->on('inventory_locations')
                ->nullOnDelete();

            $table->unique(['name', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
