<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->unique()->constrained('requests')->cascadeOnDelete();
           $table->string('request_for')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->boolean('is_transporter')->default(false);
            $table->string('attachment')->nullable();
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->index(['request_for', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_requests');
    }
};
