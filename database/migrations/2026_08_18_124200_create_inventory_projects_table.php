<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_projects', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('donor_id', 26)->nullable()->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->foreign('donor_id')
                ->references('id')
                ->on('inventory_donors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_projects');
    }
};
