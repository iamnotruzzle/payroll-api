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

    public function save(string $source, ?string $by): void
    {
        if (! in_array($source, ['manual', 'hris_db'], true)) {
            throw ValidationException::withMessages(['timekeepingSource' => 'Choose manual inputs or HRIS database timekeeping.']);
        }
        PayrollSystemSetting::query()->updateOrCreate(['key' => 'standalone_timekeeping_source'],
            ['value' => ['source' => $source], 'updated_by' => $by]);
    }

    public function databaseError(): ?string
    {
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
