<?php

namespace App\Models\Schedule;

use App\Services\Schedule\ScheduleDivisionService;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleDepartmentProfile extends PayrollSchedulerModel
{
    protected $fillable = [
        'department_id',
        'uses_units',
        'uses_floaters',
        'uses_on_call',
        'uses_swaps',
        'uses_census',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'uses_units' => 'boolean',
            'uses_floaters' => 'boolean',
            'uses_on_call' => 'boolean',
            'uses_swaps' => 'boolean',
            'uses_census' => 'boolean',
            'meta' => 'array',
        ];
    }

    public static function forDepartment(int|string $departmentId): self
    {
        $defaults = app(ScheduleDivisionService::class)
            ->profileDefaultsForDepartment((int) $departmentId);

        return static::query()->firstOrNew(
            ['department_id' => $departmentId],
            $defaults
        );
    }

    /**
     * Persist defaults when no profile row exists yet (CNO nursing vs simple).
     */
    public static function ensureForDepartment(int|string $departmentId): self
    {
        $profile = static::forDepartment($departmentId);
        if (! $profile->exists) {
            $profile->save();
        }

        return $profile->fresh() ?? $profile;
    }

    public function units(): HasMany
    {
        return $this->hasMany(ScheduleUnit::class, 'department_id', 'department_id');
    }

    public function isSimpleProfile(): bool
    {
        return ! $this->uses_units
            && ! $this->uses_floaters
            && ! $this->uses_on_call
            && ! $this->uses_swaps
            && ! $this->uses_census;
    }

    public function isNursingCapable(): bool
    {
        return $this->uses_units
            && $this->uses_floaters
            && $this->uses_on_call
            && $this->uses_swaps
            && $this->uses_census;
    }

    /**
     * Persist defaults when the row does not exist yet.
     */
    public function saveProfile(array $attributes): self
    {
        $this->fill($attributes);
        $this->save();

        return $this->fresh() ?? $this;
    }
}
