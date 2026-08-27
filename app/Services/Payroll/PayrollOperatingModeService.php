<?php

namespace App\Services\Payroll;

use App\Enums\PayrollOperatingMode;
use App\Models\Payroll\PayrollSystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PayrollOperatingModeService
{
    public function current(): PayrollOperatingMode
    {
        $forced = config('payroll_standalone.forced_mode');
        if ($forced) {
            return PayrollOperatingMode::from($forced);
        }
        if (! Schema::connection('payroll')->hasTable('payroll_system_settings')) {
            return PayrollOperatingMode::Connected;
        }
        $value = PayrollSystemSetting::query()->where('key', 'operating_mode')->value('value');
        $mode = is_array($value) ? ($value['mode'] ?? null) : $value;

        return PayrollOperatingMode::tryFrom((string) $mode) ?? PayrollOperatingMode::from(config('payroll_standalone.default_mode'));
    }

    public function allowed(): array
    {
        return collect(config('payroll_standalone.allowed_modes', []))->map(fn ($mode) => PayrollOperatingMode::tryFrom($mode))->filter()->values()->all();
    }

    public function forced(): bool
    {
        return filled(config('payroll_standalone.forced_mode'));
    }

    public function change(PayrollOperatingMode $mode, ?string $by): void
    {
        $from = $this->current();
        if ($this->forced()) {
            throw ValidationException::withMessages(['mode' => 'Mode is locked by PAYROLL_FORCE_MODE.']);
        }
        if (! in_array($mode, $this->allowed(), true)) {
            throw ValidationException::withMessages(['mode' => 'Mode is not permitted by PAYROLL_OPERATION_MODES.']);
        }
        if ($mode === PayrollOperatingMode::Standalone) {
            $readiness = app(PayrollReadinessService::class)->check();
            if (! $readiness['ready']) {
                throw ValidationException::withMessages(['mode' => 'Stand-alone readiness failed: '.implode(' ', $readiness['errors'])]);
            }
        }
        $snapshot = app(PayrollReadinessService::class)->check();
        DB::connection('payroll')->transaction(function () use ($mode, $from, $by, $snapshot) {
            PayrollSystemSetting::query()->updateOrCreate(['key' => 'operating_mode'], ['value' => ['mode' => $mode->value, 'changed_at' => now()->toIso8601String()], 'updated_by' => $by]);
            DB::connection('payroll')->table('payroll_mode_changes')->insert(['from_mode' => $from->value, 'to_mode' => $mode->value, 'changed_by' => $by, 'readiness_snapshot' => json_encode($snapshot), 'created_at' => now()]);
        });
    }
}
