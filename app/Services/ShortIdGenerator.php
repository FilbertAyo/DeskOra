<?php

namespace App\Services;

use App\Support\ShortId;
use App\Support\ShortIdSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ShortIdGenerator
{
    /**
     * @param  array<string, true>  $reserved
     */
    public function generateUnique(array $reserved = []): string
    {
        for ($attempt = 0; $attempt < 500; $attempt++) {
            $candidate = ShortId::random();
            if (isset($reserved[$candidate])) {
                continue;
            }
            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique short id.');
    }

    public function isAvailable(string $id): bool
    {
        if (! ShortId::is($id)) {
            return false;
        }

        foreach (ShortIdSchema::primaryKeyTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (DB::table($table)->where('id', $id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
