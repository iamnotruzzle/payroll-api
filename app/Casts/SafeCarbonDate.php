<?php

namespace App\Casts;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Date cast that treats MySQL zero-dates (0000-00-00) as null and returns Carbon.
 * Use when callers expect Carbon helpers (format / toDateString); prefer SafeDate
 * when the attribute is displayed as a plain Y-m-d string.
 */
class SafeCarbonDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        if ($value === null || $value === '' || (is_string($value) && str_starts_with($value, '0000-00-00'))) {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (is_string($value) && str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
