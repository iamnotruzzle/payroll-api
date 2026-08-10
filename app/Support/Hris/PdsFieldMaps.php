<?php

namespace App\Support\Hris;

use Illuminate\Support\Facades\DB;

/**
 * CS Form 212 / legacy HRIS value maps for PDS section fields.
 */
final class PdsFieldMaps
{
    /** @var array<string, string>|null */
    private static ?array $civilStatusMap = null;

    /** @var array<string, string> */
    public const EDUCATION_LEVELS = [
        '0' => 'Elementary',
        '1' => 'Secondary / High School',
        '2' => 'Vocational / Trade Course',
        '3' => 'College',
        '4' => 'Graduate Studies',
    ];

    /** @var array<string, string> */
    public const OTHER_INFO_TYPES = [
        'skill' => 'Special skills / hobbies',
        'recognition' => 'Non-academic distinctions / recognition',
        'membership' => 'Membership in association / organization',
    ];

    /** Legacy tbl_employee_otherinfo.type integers → v2 type keys. */
    /** @var array<int|string, string> */
    public const OTHER_INFO_LEGACY_TO_V2 = [
        0 => 'skill',
        1 => 'recognition',
        2 => 'membership',
        '0' => 'skill',
        '1' => 'recognition',
        '2' => 'membership',
    ];

    /** @var array<string, int> */
    public const OTHER_INFO_V2_TO_LEGACY = [
        'skill' => 0,
        'skills' => 0,
        'hobby' => 0,
        'hobbies' => 0,
        'recognition' => 1,
        'award' => 1,
        'distinction' => 1,
        'membership' => 2,
        'association' => 2,
        'organization' => 2,
    ];

    public static function educationLevelLabel(mixed $value): string
    {
        $key = trim((string) ($value ?? ''));
        if ($key === '') {
            return '';
        }

        if (isset(self::EDUCATION_LEVELS[$key])) {
            return self::EDUCATION_LEVELS[$key];
        }

        $upper = strtoupper($key);
        foreach (self::EDUCATION_LEVELS as $label) {
            if (strtoupper($label) === $upper) {
                return $label;
            }
        }

        return $key;
    }

    public static function otherInfoTypeKey(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        if (isset(self::OTHER_INFO_LEGACY_TO_V2[$raw])) {
            return self::OTHER_INFO_LEGACY_TO_V2[$raw];
        }

        $lower = strtolower($raw);
        foreach (self::OTHER_INFO_V2_TO_LEGACY as $needle => $_) {
            if ($lower === $needle || str_contains($lower, $needle)) {
                return match ((int) self::OTHER_INFO_V2_TO_LEGACY[$needle]) {
                    0 => 'skill',
                    1 => 'recognition',
                    default => 'membership',
                };
            }
        }

        return $lower;
    }

    public static function otherInfoTypeLabel(mixed $value): string
    {
        $key = self::otherInfoTypeKey($value);

        return self::OTHER_INFO_TYPES[$key] ?? ($key !== '' ? $key : '');
    }

    public static function otherInfoLegacyType(mixed $value): ?int
    {
        $key = self::otherInfoTypeKey($value);
        if ($key === '') {
            return null;
        }

        return self::OTHER_INFO_V2_TO_LEGACY[$key] ?? null;
    }

    public static function sexLabel(mixed $value): string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));

        return match ($raw) {
            'M', 'MALE' => 'Male',
            'F', 'FEMALE' => 'Female',
            default => (string) ($value ?? ''),
        };
    }

    /**
     * Resolve legacy tbl_civilstat ids (0/1/2/3) or free-text to a display label.
     * Important: civil status "0" (Single) must not be treated as empty by PHP/Blade truthiness.
     */
    public static function civilStatusLabel(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $map = self::civilStatusMap();
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        $lower = strtolower($raw);
        foreach ($map as $label) {
            if (strtolower($label) === $lower) {
                return $label;
            }
        }

        return $raw;
    }

    /**
     * @return array<string, string>
     */
    private static function civilStatusMap(): array
    {
        if (self::$civilStatusMap !== null) {
            return self::$civilStatusMap;
        }

        try {
            self::$civilStatusMap = DB::connection('hris')
                ->table('tbl_civilstat')
                ->orderBy('civilstat_id')
                ->get(['civilstat_id', 'civilstat'])
                ->mapWithKeys(fn ($row) => [(string) $row->civilstat_id => (string) $row->civilstat])
                ->all();
        } catch (\Throwable) {
            self::$civilStatusMap = [
                '0' => 'Single',
                '1' => 'Married',
                '2' => 'Widowed',
                '3' => 'Separated',
            ];
        }

        return self::$civilStatusMap;
    }

    public static function sexCode(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));

        return match ($raw) {
            'M', 'MALE' => 'M',
            'F', 'FEMALE' => 'F',
            '' => null,
            default => mb_substr($raw, 0, 1),
        };
    }

    public static function yesNoToBool(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtoupper((string) $value), ['Y', '1', 'TRUE', 'YES'], true);
    }
}
