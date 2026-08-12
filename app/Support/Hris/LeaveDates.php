<?php

namespace App\Support\Hris;

use App\Models\Hris\EmployeeLeave;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative leave day list: prefer remarks date CSV, else expand start–end.
 */
final class LeaveDates
{
    public const MODE_PICK = 'pick';

    public const MODE_WEEKDAYS = 'weekdays';

    public const MODE_CALENDAR = 'calendar';

    /**
     * @return list<string> Y-m-d strings, sorted unique
     */
    public static function for(EmployeeLeave $leave): array
    {
        $fromRemarks = self::parseCsv((string) ($leave->remarks ?? ''));
        if ($fromRemarks !== []) {
            return $fromRemarks;
        }

        $start = $leave->start_date
            ? CarbonImmutable::parse($leave->start_date)->startOfDay()
            : null;
        $end = $leave->end_date
            ? CarbonImmutable::parse($leave->end_date)->startOfDay()
            : null;

        if (! $start || ! $end) {
            return [];
        }

        return self::expandRange($start, $end, self::MODE_CALENDAR);
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public static function carbonsFor(EmployeeLeave $leave): Collection
    {
        return collect(self::for($leave))
            ->map(fn (string $date) => CarbonImmutable::parse($date)->startOfDay())
            ->values();
    }

    /**
     * @param  list<string>|string  $dates
     * @return list<string>
     */
    public static function normalize(array|string $dates): array
    {
        if (is_string($dates)) {
            return self::parseCsv($dates);
        }

        $normalized = [];
        foreach ($dates as $date) {
            $parsed = self::parseOne((string) $date);
            if ($parsed !== null) {
                $normalized[$parsed] = $parsed;
            }
        }

        $list = array_values($normalized);
        sort($list);

        return $list;
    }

    /**
     * @return list<string>
     */
    public static function parseCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Free-text notes must not be treated as dates.
        if (! self::looksLikeDateCsv($raw)) {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $parsed = self::parseOne($part);
            if ($parsed !== null) {
                $normalized[$parsed] = $parsed;
            }
        }

        $list = array_values($normalized);
        sort($list);

        return $list;
    }

    public static function looksLikeDateCsv(string $raw): bool
    {
        $raw = trim($raw);
        if ($raw === '') {
            return false;
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];
        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (self::parseOne($part) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $dates
     */
    public static function toCsv(array $dates): string
    {
        return implode(',', self::normalize($dates));
    }

    /**
     * @return list<string>
     */
    public static function expandRange(CarbonImmutable $start, CarbonImmutable $end, string $mode): array
    {
        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the start date.',
            ]);
        }

        $dates = [];
        for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
            if ($mode === self::MODE_WEEKDAYS && $cursor->isWeekend()) {
                continue;
            }
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }

    /**
     * Build selected dates from UI mode + optional explicit list.
     *
     * @param  list<string>|string|null  $selectedDates
     * @return list<string>
     */
    public static function resolveSelection(
        string $mode,
        ?string $startDate,
        ?string $endDate,
        array|string|null $selectedDates = null,
    ): array {
        $mode = in_array($mode, [self::MODE_PICK, self::MODE_WEEKDAYS, self::MODE_CALENDAR], true)
            ? $mode
            : self::MODE_WEEKDAYS;

        if ($mode === self::MODE_PICK) {
            $list = self::normalize($selectedDates ?? []);
            if ($list === [] && $startDate && $endDate) {
                // Single-day convenience when picker only set start/end.
                $list = self::expandRange(
                    CarbonImmutable::parse($startDate)->startOfDay(),
                    CarbonImmutable::parse($endDate)->startOfDay(),
                    self::MODE_CALENDAR,
                );
            }

            return $list;
        }

        if (! $startDate || ! $endDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Start and end dates are required for this mode.',
            ]);
        }

        return self::expandRange(
            CarbonImmutable::parse($startDate)->startOfDay(),
            CarbonImmutable::parse($endDate)->startOfDay(),
            $mode === self::MODE_WEEKDAYS ? self::MODE_WEEKDAYS : self::MODE_CALENDAR,
        );
    }

    public static function assertNonEmpty(array $dates, string $field = 'selected_dates'): void
    {
        if ($dates === []) {
            throw ValidationException::withMessages([
                $field => 'Select at least one leave date.',
            ]);
        }
    }

    private static function parseOne(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)
            && ! preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $value)
        ) {
            // Allow legacy "M d, Y" only when unambiguous numeric date forms fail.
            if (! preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}$/', $value)) {
                try {
                    $parsed = CarbonImmutable::parse($value)->startOfDay();
                    // Reject free text that Carbon greedily parses (e.g. "next week").
                    if (! preg_match('/\d/', $value)) {
                        return null;
                    }

                    return $parsed->toDateString();
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
