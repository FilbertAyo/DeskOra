<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_department_user', function (Blueprint $table) {
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('sub_department_id')->constrained('departments')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'sub_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_department_user');
    }
};
