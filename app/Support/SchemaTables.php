<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class SchemaTables
{
    public static function has(string $table): bool
    {
        return (bool) Cache::remember(
            'schema-has-table:'.$table,
            now()->addHour(),
            fn () => Schema::hasTable($table)
        );
    }
}
