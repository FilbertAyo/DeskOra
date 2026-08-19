<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_issues', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('stock_item_id', 26)->index();
            $table->unsignedInteger('quantity');
            $table->string('issued_to_user_id', 26)->nullable()->index();
            $table->string('issued_to_department_id', 26)->nullable()->index();
            $table->string('purpose')->nullable();
            $table->string('issued_by_user_id', 26)->index();
            $table->timestamp('issued_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('stock_item_id')->references('id')->on('inventory_stock_items')->cascadeOnDelete();
            $table->foreign('issued_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('issued_to_department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('issued_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_stock_adjustments', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('stock_item_id', 26)->index();
            $table->enum('direction', ['increase', 'decrease']);
            $table->unsignedInteger('quantity');
            $table->enum('reason_code', ['damaged', 'expired', 'miscount_correction', 'transferred', 'other']);
            $table->text('notes')->nullable();
            $table->string('adjusted_by_user_id', 26)->index();
            $table->timestamp('adjusted_at');
            $table->timestamps();

            $table->foreign('stock_item_id')->references('id')->on('inventory_stock_items')->cascadeOnDelete();
            $table->foreign('adjusted_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_adjustments');
        Schema::dropIfExists('inventory_stock_issues');
    }
};
