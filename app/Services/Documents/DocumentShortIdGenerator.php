<?php

namespace App\Services\Documents;

use App\Services\ShortIdGenerator;
use App\Support\ShortId;

/** @deprecated Use {@see ShortIdGenerator} and {@see ShortId} */
class DocumentShortIdGenerator extends ShortIdGenerator
{
    public const LENGTH = ShortId::LENGTH;

    public static function isShortId(string $value): bool
    {
        return ShortId::is($value);
    }

    public static function isLegacyUlid(string $value): bool
    {
        return ShortId::isLegacyUlid($value);
    }
}
