<?php

namespace App\Services\Documents;

use App\Models\ActionLog;
use App\Models\MonthlyReport;
use App\Models\WeeklyPlan;
use App\Models\WeeklyReport;
use RuntimeException;

class DocumentShortIdGenerator
{
    public const LENGTH = 4;

    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyz';

    public static function isShortId(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-z]{'.self::LENGTH.'}$/', $value);
    }

    public static function isLegacyUlid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-z]{26}$/', $value);
    }

    /**
     * @param  array<string, true>  $reserved
     */
    public function generateUnique(array $reserved = []): string
    {
        for ($attempt = 0; $attempt < 200; $attempt++) {
            $candidate = $this->randomId();
            if (isset($reserved[$candidate])) {
                continue;
            }
            if ($this->isAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate a unique document id.');
    }

    public function isAvailable(string $id): bool
    {
        if (! self::isShortId($id)) {
            return false;
        }

        return ! WeeklyPlan::withTrashed()->whereKey($id)->exists()
            && ! WeeklyReport::withTrashed()->whereKey($id)->exists()
            && ! MonthlyReport::withTrashed()->whereKey($id)->exists()
            && ! ActionLog::withTrashed()->whereKey($id)->exists();
    }

    private function randomId(): string
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
