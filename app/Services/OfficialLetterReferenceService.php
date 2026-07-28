<?php

namespace App\Services;

use App\Models\OfficialLetterSequence;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OfficialLetterReferenceService
{
    /**
     * Allocate the next reference for a department + letter date.
     *
     * @return array{reference_number: string, sequence: int}
     */
    public function allocate(string $departmentCode, CarbonInterface $letterDate): array
    {
        $departmentCode = strtoupper(trim($departmentCode));
        $departments = config('official-letters.departments', []);

        if (! is_array($departments) || ! array_key_exists($departmentCode, $departments)) {
            throw new InvalidArgumentException("Invalid official letter department code: {$departmentCode}");
        }

        $yearMonth = $letterDate->format('Y-m');
        $mmm = strtoupper($letterDate->format('M'));
        $yy = $letterDate->format('y');
        $day = $letterDate->format('d');

        return DB::transaction(function () use ($departmentCode, $yearMonth, $mmm, $yy, $day) {
            $this->ensureSequenceRow($departmentCode, $yearMonth);

            /** @var OfficialLetterSequence $sequenceRow */
            $sequenceRow = OfficialLetterSequence::query()
                ->where('department_code', $departmentCode)
                ->where('year_month', $yearMonth)
                ->lockForUpdate()
                ->firstOrFail();

            $sequenceRow->last_sequence = (int) $sequenceRow->last_sequence + 1;
            $sequenceRow->save();

            $sequence = (int) $sequenceRow->last_sequence;
            $paddedSequence = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

            $referenceNumber = $this->formatReference(
                $departmentCode,
                $mmm,
                $yy,
                $day,
                $paddedSequence,
            );

            return [
                'reference_number' => $referenceNumber,
                'sequence' => $sequence,
            ];
        });
    }

    public function formatReference(
        string $departmentCode,
        string $mmm,
        string $yy,
        string $day,
        string $sequence,
    ): string {
        $prefix = (string) config('official-letters.org_prefix', 'REF NGO');
        $template = (string) config(
            'official-letters.reference_format',
            '{prefix}/{department}/{mmm}-{yy}/{day}-{sequence}'
        );

        return strtr($template, [
            '{prefix}' => $prefix,
            '{department}' => $departmentCode,
            '{mmm}' => $mmm,
            '{yy}' => $yy,
            '{day}' => $day,
            '{sequence}' => $sequence,
        ]);
    }

    private function ensureSequenceRow(string $departmentCode, string $yearMonth): void
    {
        $exists = OfficialLetterSequence::query()
            ->where('department_code', $departmentCode)
            ->where('year_month', $yearMonth)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            OfficialLetterSequence::query()->create([
                'department_code' => $departmentCode,
                'year_month' => $yearMonth,
                'last_sequence' => 0,
            ]);
        } catch (QueryException $e) {
            // Concurrent insert — unique constraint; continue to lock existing row.
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // SQLSTATE 23000 (integrity), MySQL 1062, SQLite / Postgres unique violations
        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === 1062
            || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
