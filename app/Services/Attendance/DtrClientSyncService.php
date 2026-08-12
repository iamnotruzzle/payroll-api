<?php

namespace App\Services\Attendance;

use App\Models\Hris\EmployeeDtr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Offline/client batch sync compatible with legacy POST api/dtr/client/sync.
 * Idempotent upserts by emp_id + attendance_date; fills empty punch slots only.
 */
class DtrClientSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array{total_records: int, synced: int, failed: bool, errors: array<int, mixed>}
     */
    public function sync(array $payload): array
    {
        $syncCount = 0;
        $errors = [];

        foreach ($payload as $dtr) {
            try {
                $row = Validator::make(is_array($dtr) ? $dtr : [], [
                    'emp_id' => ['required', 'string'],
                    'attendance_date' => ['required', 'date'],
                    'timein_am' => ['nullable', 'string'],
                    'timeout_am' => ['nullable', 'string'],
                    'timein_pm' => ['nullable', 'string'],
                    'timeout_pm' => ['nullable', 'string'],
                    'timeout_nextday' => ['nullable'],
                    'machine_id' => ['nullable', 'string'],
                ])->validate();

                $changed = DB::connection('hris')->transaction(function () use ($row): bool {
                    $existing = EmployeeDtr::query()
                        ->where('emp_id', $row['emp_id'])
                        ->whereDate('dtr_date', $row['attendance_date'])
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        // Legacy behavior: only fill empty slots (do not overwrite existing punches).
                        if (blank($existing->timein_am) && filled($row['timein_am'] ?? null)) {
                            $existing->timein_am = $row['timein_am'];
                        }
                        if (blank($existing->timeout_am) && filled($row['timeout_am'] ?? null)) {
                            $existing->timeout_am = $row['timeout_am'];
                        }
                        if (blank($existing->timein_pm) && filled($row['timein_pm'] ?? null)) {
                            $existing->timein_pm = $row['timein_pm'];
                        }
                        if (blank($existing->timeout_pm) && filled($row['timeout_pm'] ?? null)) {
                            $existing->timeout_pm = $row['timeout_pm'];
                        }

                        if (blank($existing->timeout_nextday) && ! empty($row['timeout_nextday'])) {
                            $existing->timeout_nextday = $row['timeout_nextday'];
                        }

                        if (blank($existing->machine_id) && ! empty($row['machine_id'])) {
                            $existing->machine_id = $row['machine_id'];
                        }

                        if ($existing->isDirty()) {
                            $existing->save();

                            return true;
                        }

                        return false;
                    }

                    EmployeeDtr::query()->create([
                        'emp_id' => $row['emp_id'],
                        'dtr_date' => $row['attendance_date'],
                        'timein_am' => $row['timein_am'] ?? null,
                        'timeout_am' => $row['timeout_am'] ?? null,
                        'timein_pm' => $row['timein_pm'] ?? null,
                        'timeout_pm' => $row['timeout_pm'] ?? null,
                        'timeout_nextday' => $row['timeout_nextday'] ?? null,
                        'machine_id' => $row['machine_id'] ?? null,
                    ]);

                    return true;
                });

                if ($changed) {
                    $syncCount++;
                }
            } catch (ValidationException $e) {
                $errors[] = [
                    'row' => $dtr,
                    'messages' => $e->errors(),
                ];
            } catch (\Throwable) {
                $errors[] = $dtr;
            }
        }

        return [
            'total_records' => count($payload),
            'synced' => $syncCount,
            'failed' => false,
            'errors' => $errors,
        ];
    }
}
