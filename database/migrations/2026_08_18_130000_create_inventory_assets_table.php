<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('asset_code')->unique();
            $table->string('name');
            $table->string('serial_number')->nullable();
            $table->string('category_id', 26)->nullable()->index();
            $table->text('description')->nullable();
            $table->date('date_received')->nullable();
            $table->enum('source_type', ['purchase', 'donor', 'grant_project', 'transfer', 'other'])->default('other');
            $table->string('donor_id', 26)->nullable()->index();
            $table->string('project_id', 26)->nullable()->index();
            $table->string('supplier_id', 26)->nullable()->index();
            $table->decimal('purchase_value', 14, 2)->nullable();
            $table->decimal('estimated_value', 14, 2)->nullable();
            $table->text('warranty_info')->nullable();
            $table->string('location_id', 26)->nullable()->index();
            $table->string('department_id', 26)->nullable()->index();
            $table->string('responsible_user_id', 26)->nullable()->index();
            $table->enum('condition', ['new', 'good', 'fair', 'needs_repair', 'damaged'])->default('good');
            $table->enum('status', ['available', 'assigned', 'borrowed', 'under_repair', 'missing', 'disposed'])->default('available')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('inventory_categories')->nullOnDelete();
            $table->foreign('donor_id')->references('id')->on('inventory_donors')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('inventory_projects')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('inventory_suppliers')->nullOnDelete();
            $table->foreign('location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_assets');
    }
};
