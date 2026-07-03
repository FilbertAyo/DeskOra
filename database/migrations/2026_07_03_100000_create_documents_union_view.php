<?php

use App\Database\DocumentsUnionView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        if (! DocumentsUnionView::canCreate()) {
            return;
        }

        DocumentsUnionView::recreate();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS documents');
    }
};
