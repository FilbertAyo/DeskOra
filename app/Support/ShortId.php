<?php

namespace App\Support;

final class ShortId
{
    public const LENGTH = 4;

    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    public static function pattern(): string
    {
        return '/^[0-9a-z]{'.self::LENGTH.'}$/';
    }

    public static function is(string $value): bool
    {
        return (bool) preg_match(self::pattern(), $value);
    }

    public static function isLegacyUlid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-z]{26}$/', $value);
    }

    public static function random(): string
    {
        $alphabet = self::ALPHABET;
        $maxIndex = strlen($alphabet) - 1;
        $id = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $id .= $alphabet[random_int(0, $maxIndex)];
        }

        return $id;
    }
}
