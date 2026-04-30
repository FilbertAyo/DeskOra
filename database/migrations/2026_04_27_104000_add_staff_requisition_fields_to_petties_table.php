<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally no-op: requisition fields are now stored in `requisition_requests`
        // and approval decisions are tracked in `request_approvals`.
    }

    public function down(): void
    {
        // Intentionally no-op.
    }
};
