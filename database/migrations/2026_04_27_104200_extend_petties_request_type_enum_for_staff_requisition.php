<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally no-op: request types are now managed in `requests.request_type`.
    }

    public function down(): void
    {
        // Intentionally no-op.
    }
};
