<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('receipt_number')->unique();
            $table->enum('source_type', ['purchase', 'donor', 'grant_project', 'transfer', 'other'])->default('purchase');
            $table->date('received_at');
            $table->string('supplier_id', 26)->nullable()->index();
            $table->string('donor_id', 26)->nullable()->index();
            $table->string('project_id', 26)->nullable()->index();
            $table->string('location_id', 26)->nullable()->index();
            $table->string('received_by_user_id', 26)->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('inventory_suppliers')->nullOnDelete();
            $table->foreign('donor_id')->references('id')->on('inventory_donors')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('inventory_projects')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('received_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_receipt_lines', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('receipt_id', 26)->index();
            $table->enum('item_type', ['asset', 'stock']);
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('category_id', 26)->nullable()->index();
            $table->string('stock_item_id', 26)->nullable()->index();
            $table->string('serial_number')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedInteger('minimum_level')->nullable();
            $table->decimal('unit_value', 14, 2)->nullable();
            $table->enum('condition', ['new', 'good', 'fair', 'needs_repair', 'damaged'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('receipt_id')->references('id')->on('inventory_receipts')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('inventory_categories')->nullOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('inventory_stock_items')->nullOnDelete();
        });

        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->string('receipt_id', 26)->nullable()->after('notes')->index();
            $table->foreign('receipt_id')->references('id')->on('inventory_receipts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropForeign(['receipt_id']);
            $table->dropColumn('receipt_id');
        });

        Schema::dropIfExists('inventory_receipt_lines');
        Schema::dropIfExists('inventory_receipts');
    }
};
