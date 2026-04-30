<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('request_id')->unique()->constrained('requests')->cascadeOnDelete();
            $table->string('project_program')->nullable();
            $table->string('activity_code')->nullable();
            $table->string('activity_title')->nullable();
            $table->string('budget_code')->nullable();
            $table->date('date_of_request')->nullable();
            $table->date('date_of_requirement')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['activity_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_requests');
    }
};
