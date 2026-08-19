<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_borrow_requests', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->string('request_number')->unique();
            $table->enum('item_type', ['asset', 'stock']);
            $table->string('asset_id', 26)->nullable()->index();
            $table->string('stock_item_id', 26)->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('borrower_user_id', 26)->index();
            $table->string('purpose');
            $table->date('expected_return_date');
            $table->enum('status', ['requested', 'approved', 'rejected', 'issued', 'returned'])->default('requested')->index();
            $table->string('requested_by_user_id', 26)->index();
            $table->string('reviewed_by_user_id', 26)->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('issued_by_user_id', 26)->nullable()->index();
            $table->timestamp('issued_at')->nullable();
            $table->string('returned_by_user_id', 26)->nullable()->index();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedInteger('quantity_returned')->nullable();
            $table->enum('return_condition', ['good', 'damaged', 'missing'])->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('id')->on('inventory_assets')->nullOnDelete();
            $table->foreign('stock_item_id')->references('id')->on('inventory_stock_items')->nullOnDelete();
            $table->foreign('borrower_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('requested_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('reviewed_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('issued_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('returned_by_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_borrow_requests');
    }
};
