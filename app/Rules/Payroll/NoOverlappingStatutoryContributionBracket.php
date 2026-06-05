<?php

namespace App\Rules\Payroll;

use App\Models\Payroll\PayrollStatutoryContributionBracket;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class NoOverlappingStatutoryContributionBracket implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function __construct(
        private readonly ?int $contributionId = null,
        private readonly ?int $ignoreBracketId = null,
    ) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contributionId = $this->contributionId ?? $this->integerValue($this->data['selectedContributionId'] ?? null);
        $ignoreBracketId = $this->ignoreBracketId ?? $this->integerValue($this->data['editingBracketId'] ?? null);
        $minSalary = $this->floatValue($this->data['minSalary'] ?? $value);
        $maxSalaryInput = $this->data['maxSalary'] ?? null;
        $maxSalary = $this->nullableFloatValue($maxSalaryInput);

        if (
            $contributionId === null
            || $minSalary === null
            || ($maxSalaryInput !== null && $maxSalaryInput !== '' && $maxSalary === null)
            || ($maxSalary !== null && $maxSalary < $minSalary)
        ) {
            return;
        }

        $effectiveStartInput = $this->data['effectiveStart'] ?? null;
        $effectiveEndInput = $this->data['effectiveEnd'] ?? null;
        $effectiveStart = $this->nullableDateString($effectiveStartInput);
        $effectiveEnd = $this->nullableDateString($effectiveEndInput);

        if (
            ($effectiveStartInput !== null && $effectiveStartInput !== '' && $effectiveStart === null)
            || ($effectiveEndInput !== null && $effectiveEndInput !== '' && $effectiveEnd === null)
            || ($effectiveStart !== null && $effectiveEnd !== null && $effectiveEnd < $effectiveStart)
        ) {
            return;
        }

        $overlapExists = PayrollStatutoryContributionBracket::query()
            ->where('statutory_contribution_id', $contributionId)
            ->when($ignoreBracketId !== null, fn ($query) => $query->whereKeyNot($ignoreBracketId))
            ->when($effectiveEnd !== null, fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereNull('effective_start')
                    ->orWhereDate('effective_start', '<=', $effectiveEnd)))
            ->when($effectiveStart !== null, fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereNull('effective_end')
                    ->orWhereDate('effective_end', '>=', $effectiveStart)))
            ->when($maxSalary !== null, fn ($query) => $query->where('min_salary', '<=', $maxSalary))
            ->where(fn ($query) => $query
                ->whereNull('max_salary')
                ->orWhere('max_salary', '>=', $minSalary))
            ->exists();

        if ($overlapExists) {
            $fail('This bracket overlaps an existing effective-date and salary-range bracket for the selected contribution.');
        }
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function nullableFloatValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->floatValue($value);
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
