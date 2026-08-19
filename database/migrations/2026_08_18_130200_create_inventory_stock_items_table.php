<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_items', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('category_id', 26)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('quantity_received')->default(0);
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->unsignedInteger('quantity_remaining')->default(0);
            $table->unsignedInteger('minimum_level')->default(0);
            $table->string('location_id', 26)->nullable()->index();
            $table->string('supplier_id', 26)->nullable()->index();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('inventory_categories')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('inventory_suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_items');
    }
};
