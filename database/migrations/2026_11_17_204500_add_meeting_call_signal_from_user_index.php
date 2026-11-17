<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_call_signals', function (Blueprint $table) {
            $table->index(['meeting_id', 'from_user_id', 'consumed_at'], 'meeting_call_signals_meeting_from_consumed_index');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_call_signals', function (Blueprint $table) {
            $table->dropIndex('meeting_call_signals_meeting_from_consumed_index');
        });
    }
};
