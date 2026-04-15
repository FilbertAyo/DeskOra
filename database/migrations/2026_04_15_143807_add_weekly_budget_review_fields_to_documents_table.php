<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('budget_review_status', ['pending', 'approved', 'rejected'])
                ->nullable()
                ->after('review_rejection_reason');
            $table->foreignUlid('budget_reviewed_by')
                ->nullable()
                ->after('budget_review_status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('budget_reviewed_at')->nullable()->after('budget_reviewed_by');
            $table->decimal('budget_provided_amount', 14, 2)->nullable()->after('budget_reviewed_at');
            $table->text('budget_review_note')->nullable()->after('budget_provided_amount');

            $table->index('budget_review_status');
            $table->index('budget_reviewed_by');
        });

        DB::table('documents')
            ->select(['id', 'content'])
            ->where('type', 'weekly_plan')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $content = json_decode((string) $row->content, true);
                    if (! is_array($content)) {
                        continue;
                    }

                    $budgetRows = $content['budget'] ?? [];
                    if (! is_array($budgetRows)) {
                        continue;
                    }

                    $hasBudgetItems = collect($budgetRows)->contains(function ($budgetRow) {
                        if (! is_array($budgetRow)) {
                            return false;
                        }

                        $item = trim((string) ($budgetRow['item'] ?? ''));
                        $description = trim((string) ($budgetRow['description_purpose'] ?? ''));
                        $amount = trim((string) ($budgetRow['amount'] ?? ''));

                        return $item !== '' || $description !== '' || $amount !== '';
                    });

                    if (! $hasBudgetItems) {
                        continue;
                    }

                    DB::table('documents')
                        ->where('id', $row->id)
                        ->whereNull('budget_review_status')
                        ->update(['budget_review_status' => 'pending']);
                }
            }, 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['budget_review_status']);
            $table->dropIndex(['budget_reviewed_by']);
            $table->dropConstrainedForeignId('budget_reviewed_by');
            $table->dropColumn([
                'budget_review_status',
                'budget_reviewed_at',
                'budget_provided_amount',
                'budget_review_note',
            ]);
        });
    }
};
