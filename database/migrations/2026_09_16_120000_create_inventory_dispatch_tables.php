<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_dispatches', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('dispatch_number')->unique();
            $table->string('title');
            $table->string('purpose');
            $table->string('destination');
            $table->date('dispatch_date');
            $table->date('expected_return_date');
            $table->enum('status', ['draft', 'ready', 'dispatched', 'returning', 'returned', 'closed'])
                ->default('draft')
                ->index();
            $table->text('notes')->nullable();
            $table->string('created_by_user_id', 26)->index();
            $table->string('dispatched_by_user_id', 26)->nullable()->index();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('return_started_by_user_id', 26)->nullable()->index();
            $table->timestamp('return_started_at')->nullable();
            $table->string('returned_by_user_id', 26)->nullable()->index();
            $table->timestamp('returned_at')->nullable();
            $table->string('closed_by_user_id', 26)->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('dispatched_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('return_started_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('returned_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('closed_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_dispatch_responsible_users', function (Blueprint $table) {
            $table->string('dispatch_id', 26);
            $table->string('user_id', 26);
            $table->timestamps();

            $table->primary(['dispatch_id', 'user_id']);
            $table->foreign('dispatch_id')->references('id')->on('inventory_dispatches')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('inventory_dispatch_items', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('dispatch_id', 26)->index();
            $table->enum('item_type', ['asset', 'stock']);
            $table->string('asset_id', 26)->nullable()->index();
            $table->string('stock_item_id', 26)->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('original_status')->nullable();
            $table->string('original_location_id', 26)->nullable()->index();
            $table->string('original_responsible_user_id', 26)->nullable()->index();
            $table->string('original_department_id', 26)->nullable()->index();
            $table->string('original_condition')->nullable();
            $table->string('condition_at_dispatch')->nullable();
            $table->enum('return_outcome', ['received_good', 'received_damaged', 'missing'])->nullable();
            $table->unsignedInteger('quantity_returned')->nullable();
            $table->unsignedInteger('quantity_consumed')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('inventory_dispatches')->cascadeOnDelete();
            $table->foreign('asset_id')->references('id')->on('inventory_assets')->restrictOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('inventory_stock_items')->restrictOnDelete();
            $table->foreign('original_location_id')->references('id')->on('inventory_locations')->nullOnDelete();
            $table->foreign('original_responsible_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('original_department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_dispatch_items');
        Schema::dropIfExists('inventory_dispatch_responsible_users');
        Schema::dropIfExists('inventory_dispatches');
    }
};
