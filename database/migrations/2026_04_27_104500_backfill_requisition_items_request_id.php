<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('requisition_items') || ! DB::getSchemaBuilder()->hasTable('request_approvals')) {
            return;
        }

        if (! Schema::hasColumn('requisition_items', 'request_id')) {
            Schema::table('requisition_items', function (Blueprint $table) {
                $table->foreignUlid('request_id')->nullable()->after('id')->constrained('requests')->cascadeOnDelete();
                $table->index(['request_id', 'serial_no']);
            });
        }

        $mappings = DB::table('request_approvals')
            ->where('step', 'system')
            ->where('action', 'created')
            ->whereRaw("JSON_EXTRACT(meta, '$.source') = 'legacy-petty'")
            ->select('request_id', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.legacy_petty_id')) as legacy_petty_id"))
            ->get();

        foreach ($mappings as $mapping) {
            DB::table('requisition_items')
                ->where('petty_id', $mapping->legacy_petty_id)
                ->whereNull('request_id')
                ->update(['request_id' => $mapping->request_id]);
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('requisition_items')) {
            return;
        }

        if (Schema::hasColumn('requisition_items', 'request_id')) {
            DB::table('requisition_items')->update(['request_id' => null]);
        }
    }
};
