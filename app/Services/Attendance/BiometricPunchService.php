<?php

namespace App\Services\Attendance;

use App\Models\Hris\Employee;
use App\Models\Hris\EmployeeDtr;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Biometric punch writer compatible with legacy HRIS POST dtr/new.
 * Writes synchronously to tbl_employee_dtr (same table as Time Punch / DTR Encoding).
 */
class BiometricPunchService
{
    private string $timeField = '';

    private string $meridiem = '';

    private CarbonImmutable $now;

    private string $empId = '';

    private string $machineId = '';

    private bool $isNoonBreak = false;

    private bool $isEarlyNoonBreak = false;

    private string $writeField = '';

    private string $timeinAm = '';

    private string $timeoutAm = '';

    private string $timeinPm = '';

    private string $timeoutPm = '';

    /**
     * @return array{field: string, message: string, status: string, timein_am: string, timeout_am: string, timein_pm: string, timeout_pm: string}
     */
    public function punch(string $empId, string $machineId, int|string $innout): array
    {
        $this->empId = $empId;
        $this->machineId = $machineId !== '' ? $machineId : '0';
        $this->timeField = ((string) $innout) === '1' ? 'OUT' : 'IN';
        $this->now = CarbonImmutable::now();
        $dateToday = $this->now->toDateString();
        $this->meridiem = $this->now->format('A');
        $this->isNoonBreak = $this->now->between(
            $this->now->setTime(12, 0, 0),
            $this->now->setTime(13, 0, 0)
        );
        $this->isEarlyNoonBreak = $this->now->between(
            $this->now->setTime(11, 0, 0),
            $this->now->setTime(12, 0, 0)
        );
        $this->writeField = strtolower('TIME'.$this->timeField.'_'.$this->meridiem);
        $this->timeinAm = '';
        $this->timeoutAm = '';
        $this->timeinPm = '';
        $this->timeoutPm = '';

        return DB::connection('hris')->transaction(function () use ($dateToday): array {
            Employee::query()
                ->whereKey($this->empId)
                ->lockForUpdate()
                ->firstOrFail();

            $dtrs = EmployeeDtr::query()
                ->where('emp_id', $this->empId)
                ->orderByDesc('dtr_id')
                ->limit(2)
                ->get();

            if ($dtrs->isEmpty()) {
                $result = $this->addFirstDtr();
            } else {
                $latest = $dtrs[0];
                $latestDate = $latest->dtr_date?->toDateString() ?? (string) $latest->dtr_date;

                if ($latestDate === $dateToday) {
                    $result = $this->checkPresentDtr($latest);
                    if ($this->timeinAm === '' && $this->timeinPm === '' && isset($dtrs[1])) {
                        $previous = $dtrs[1];
                        $this->timeinAm = filled($previous->timein_am)
                            ? $previous->timein_am.', '.$previous->dtr_date?->toDateString()
                            : '';
                        $this->timeinPm = filled($previous->timein_pm)
                            ? $previous->timein_pm.', '.$previous->dtr_date?->toDateString()
                            : '';
                    }
                } else {
                    $result = $this->checkPreviousDtr($latest);
                    if ($result['status'] === 'Warning!') {
                        $this->timeinAm = '';
                        $this->timeoutAm = '';
                        $this->timeinPm = '';
                        $this->timeoutPm = '';
                    }
                }
            }

            return [
                'field' => $this->writeField,
                'message' => $result['message'],
                'status' => $result['status'],
                'timein_am' => is_string($this->timeinAm) ? $this->timeinAm : '',
                'timeout_am' => is_string($this->timeoutAm) ? $this->timeoutAm : '',
                'timein_pm' => is_string($this->timeinPm) ? $this->timeinPm : '',
                'timeout_pm' => is_string($this->timeoutPm) ? $this->timeoutPm : '',
            ];
        });
    }

    /**
     * @return array{message: string, status: string}
     */
    private function checkPresentDtr(EmployeeDtr $presentDtr): array
    {
        $timeinAm = $presentDtr->timein_am;
        $timeoutAm = $presentDtr->timeout_am;
        $timeinPm = $presentDtr->timein_pm;
        $timeoutPm = $presentDtr->timeout_pm;
        $this->timeinAm = (string) ($timeinAm ?? '');
        $this->timeoutAm = (string) ($timeoutAm ?? '');
        $this->timeinPm = (string) ($timeinPm ?? '');
        $this->timeoutPm = (string) ($timeoutPm ?? '');

        if ($this->timeField === 'IN') {
            $message = 'You are already TIMED IN.';
            $status = 'Warning!';

            if (is_null($timeinPm)) {
                if (is_null($timeinAm)) {
                    $this->applyPresent($presentDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                } elseif (isset($timeoutAm)) {
                    if (isset($timeoutPm)) {
                        if (strtotime((string) $timeinAm) > strtotime((string) $timeoutAm)) {
                            $this->applyPresent($presentDtr, $this->writeField);
                            $message = 'Thank You.';
                            $status = 'Verified';
                        }
                    } elseif ($this->isEarlyNoonBreak || $this->meridiem === 'PM') {
                        if (strtotime((string) $timeoutAm) > strtotime((string) $timeinAm)) {
                            $this->writeField = 'timein_pm';
                            $this->applyPresent($presentDtr, $this->writeField);
                            $message = 'Thank You.';
                            $status = 'Verified';
                        }
                    }
                } elseif (isset($timeoutPm)) {
                    $this->applyPresent($presentDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                }
            }
        } else {
            $message = 'You are already TIMED OUT.';
            $status = 'Warning!';

            if (is_null($timeoutPm)) {
                if (is_null($timeinPm)) {
                    if (isset($timeinAm)) {
                        if (is_null($timeoutAm)) {
                            if ($this->isNoonBreak) {
                                $this->writeField = 'timeout_am';
                            }
                            $this->applyPresent($presentDtr, $this->writeField);
                            $message = 'Thank You.';
                            $status = 'Verified';
                        } elseif (strtotime((string) $timeinAm) > strtotime((string) $timeoutAm)) {
                            $this->applyPresent($presentDtr, $this->writeField);
                            $message = 'Thank You.';
                            $status = 'Verified';
                        }
                    }
                } else {
                    $this->applyPresent($presentDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                }
            } elseif (isset($timeinAm) && is_null($timeoutAm) && isset($timeinPm)) {
                if (strtotime((string) $timeinPm) > strtotime((string) $timeoutPm)) {
                    $this->adjustPresent($presentDtr, 'timeout_am', $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                }
            }
        }

        return ['message' => $message, 'status' => $status];
    }

    /**
     * @return array{message: string, status: string}
     */
    private function checkPreviousDtr(EmployeeDtr $previousDtr): array
    {
        $timeinAm = $previousDtr->timein_am;
        $timeoutAm = $previousDtr->timeout_am;
        $timeinPm = $previousDtr->timein_pm;
        $timeoutPm = $previousDtr->timeout_pm;

        if ($this->timeField === 'IN') {
            $message = 'No TIME OUT from PREVIOUS SHIFT yet.';
            $status = 'Warning!';

            if (is_null($timeoutPm)) {
                if (is_null($timeinPm) && isset($timeoutAm)) {
                    if (isset($timeinAm)) {
                        if (strtotime((string) $timeoutAm) > strtotime((string) $timeinAm)) {
                            $this->createToday($this->writeField);
                            $message = 'Thank You.';
                            $status = 'Verified';
                        }
                    } else {
                        $this->createToday($this->writeField);
                        $message = 'Thank You.';
                        $status = 'Verified';
                    }
                }
            } elseif (is_null($timeinPm)) {
                $this->createToday($this->writeField);
                $message = 'Thank You.';
                $status = 'Verified';
            } elseif (isset($timeinAm) && isset($timeoutAm)) {
                $this->createToday($this->writeField);
                $message = 'Thank You.';
                $status = 'Verified';
            } elseif (is_null($timeinAm) && strtotime((string) $timeoutPm) > strtotime((string) $timeinPm)) {
                $this->createToday($this->writeField);
                $message = 'Thank You.';
                $status = 'Verified';
            }
        } else {
            $message = "You don't have any TIME IN yet.";
            $status = 'Warning!';

            if (isset($timeoutPm)) {
                if (isset($timeinPm) && is_null($timeoutAm) && strtotime((string) $timeinPm) > strtotime((string) $timeoutPm)) {
                    $this->createTodayWithPreviousTimeout($previousDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                }
            } elseif (isset($timeinPm)) {
                $this->createTodayWithPreviousTimeout($previousDtr, $this->writeField);
                $message = 'Thank You.';
                $status = 'Verified';
            } elseif (isset($timeinAm)) {
                if (is_null($timeoutAm)) {
                    $this->createTodayWithPreviousTimeout($previousDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                } elseif (strtotime((string) $timeinAm) > strtotime((string) $timeoutAm)) {
                    $this->createTodayWithPreviousTimeout($previousDtr, $this->writeField);
                    $message = 'Thank You.';
                    $status = 'Verified';
                }
            }
        }

        return ['message' => $message, 'status' => $status];
    }

    /**
     * @return array{message: string, status: string}
     */
    private function addFirstDtr(): array
    {
        if ($this->timeField === 'IN') {
            $this->createToday($this->writeField);

            return ['message' => 'Thank You.', 'status' => 'Verified'];
        }

        return ['message' => "You don't have any TIME IN yet.", 'status' => 'Warning!'];
    }

    private function applyPresent(EmployeeDtr $dtr, string $field): void
    {
        $this->assertWritableField($field);
        if (blank($dtr->{$field})) {
            $dtr->{$field} = $this->now->toTimeString();
            if (blank($dtr->machine_id)) {
                $dtr->machine_id = $this->machineId;
            }
            $dtr->save();
        }
    }

    private function adjustPresent(EmployeeDtr $dtr, string $toField, string $writeField): void
    {
        $this->assertWritableField($toField);
        $this->assertWritableField($writeField);
        $dtr->{$toField} = $dtr->{$writeField};
        $dtr->{$writeField} = $this->now->toTimeString();
        $dtr->save();
    }

    private function createToday(string $field): void
    {
        $this->assertWritableField($field);
        $date = $this->now->toDateString();

        $existing = EmployeeDtr::query()
            ->where('emp_id', $this->empId)
            ->whereDate('dtr_date', $date)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            $this->applyPresent($existing, $field);

            return;
        }

        EmployeeDtr::query()->create([
            'emp_id' => $this->empId,
            'dtr_date' => $date,
            $field => $this->now->toTimeString(),
            'machine_id' => $this->machineId,
        ]);
    }

    private function createTodayWithPreviousTimeout(EmployeeDtr $previousDtr, string $field): void
    {
        if (blank($previousDtr->timeout_nextday)) {
            $previousDtr->timeout_nextday = $this->now->toDateTimeString();
            $previousDtr->save();
        }

        $this->createToday($field);
    }

    private function assertWritableField(string $field): void
    {
        $allowed = ['timein_am', 'timeout_am', 'timein_pm', 'timeout_pm'];
        if (! in_array($field, $allowed, true)) {
            throw new InvalidArgumentException("Invalid DTR field [{$field}].");
        }
    }
}
