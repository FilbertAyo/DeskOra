<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_letters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference_number')->unique();
            $table->string('department_code', 16);
            $table->date('letter_date');
            $table->unsignedInteger('sequence');
            $table->text('recipient');
            $table->string('subject');
            $table->string('salutation')->default('Dear Sir/Madam,');
            $table->text('body');
            $table->string('closing')->default('In service,');
            $table->foreignUlid('signatory_id')->constrained('users')->restrictOnDelete();
            $table->string('signatory_name');
            $table->string('signatory_title');
            $table->string('signature_mode', 20)->default('digital');
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['department_code', 'letter_date']);
            $table->index('letter_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_letters');
    }
};
