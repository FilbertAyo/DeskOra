<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('official_letter_download_requests')) {
            Schema::create('official_letter_download_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignUlid('official_letter_id')->constrained('official_letters')->cascadeOnDelete();
                $table->foreignUlid('requested_by')->constrained('users')->restrictOnDelete();
                $table->string('status', 20);
                $table->text('request_note')->nullable();
                $table->text('decision_note')->nullable();
                $table->foreignUlid('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();

                $table->index(['official_letter_id', 'status'], 'ol_dl_req_letter_status_idx');
                $table->index('status', 'ol_dl_req_status_idx');
            });

            return;
        }

        Schema::table('official_letter_download_requests', function (Blueprint $table) {
            if (! $this->hasIndex('official_letter_download_requests', 'ol_dl_req_letter_status_idx')) {
                $table->index(['official_letter_id', 'status'], 'ol_dl_req_letter_status_idx');
            }

            if (! $this->hasIndex('official_letter_download_requests', 'ol_dl_req_status_idx')) {
                $table->index('status', 'ol_dl_req_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_letter_download_requests');
    }

    private function hasIndex(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ((int) ($result[0]->aggregate ?? 0)) > 0;
    }
};
