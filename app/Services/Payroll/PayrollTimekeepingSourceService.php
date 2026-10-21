<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSystemSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PayrollTimekeepingSourceService
{
    public function standalone(): bool
    {
        return app(PayrollOperatingModeService::class)->current() === PayrollOperatingMode::Standalone;
    }

    public function source(): string
    {
        $saved = Schema::connection('payroll')->hasTable('payroll_system_settings')
            ? PayrollSystemSetting::query()->where('key', 'standalone_timekeeping_source')->value('value') : null;

        return $saved['source'] ?? config('payroll_standalone.timekeeping_source', 'manual');
    }

    public function usesHrisDatabase(): bool
    {
        return $this->standalone() && $this->source() === 'hris_db';
    }

    public function usesHrisApi(): bool
    {
        return $this->standalone() && $this->source() === 'hris_api';
    }

    public function attendancePeriod(string $payrollPeriod): string
    {
        return $this->usesHrisApi()
            ? \Carbon\CarbonImmutable::createFromFormat('!Y-m', $payrollPeriod)->subMonthNoOverflow()->format('Y-m')
            : $payrollPeriod;
    }

    public function usesLocalSnapshot(): bool
    {
        if (! $this->usesHrisDatabase()) {
            return false;
        }
        $value = PayrollSystemSetting::query()->where('key', 'standalone_timekeeping_source')->value('value');

        return (bool) ($value['snapshot'] ?? false);
    }

    public function save(string $source, ?string $by, bool $snapshot = false): void
    {
        if (! in_array($source, ['manual', 'hris_db', 'hris_api'], true)) {
            throw ValidationException::withMessages(['timekeepingSource' => 'Choose manual inputs, HRIS API summaries, or HRIS database timekeeping.']);
        }
        PayrollSystemSetting::query()->updateOrCreate(['key' => 'standalone_timekeeping_source'],
            ['value' => ['source' => $source, 'snapshot' => $snapshot], 'updated_by' => $by]);
    }

    public function databaseError(): ?string
    {
        if ($this->usesLocalSnapshot()) {
            return Schema::connection('payroll')->hasTable('payroll_hris_dtr_snapshots')
                ? null : 'Run the HRIS timekeeping snapshot migration on the payroll database.';
        }
        try {
            if (! Schema::connection('hris')->hasTable('tbl_employee_dtr')) {
                return 'HRIS timekeeping is unavailable: tbl_employee_dtr was not found.';
            }
        } catch (\Throwable) {
            return 'HRIS timekeeping database is unavailable. Check the HRIS database connection.';
        }

        return null;
    }

    public function assertAvailable(): void
    {
        if ($this->standalone()) {
            if (! $this->usesHrisDatabase()) {
                throw ValidationException::withMessages(['timekeeping' => 'Enable HRIS database timekeeping in Payroll System Management to use DTR/MRA in standalone mode.']);
            }
            if ($error = $this->databaseError()) {
                throw ValidationException::withMessages(['timekeeping' => $error]);
            }
        }
    }
}
