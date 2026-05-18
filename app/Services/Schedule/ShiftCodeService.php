<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ShiftCode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ShiftCodeService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function list(array $filters = []): LengthAwarePaginator
    {
        return ShiftCode::query()
            ->when(array_key_exists('department_id', $filters), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereNull('department_id')->orWhere('department_id', $filters['department_id']);
                });
            })
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate($filters['per_page'] ?? 20);
    }

    public function save(array $data, ?ShiftCode $shiftCode = null, ?string $performedBy = null): ShiftCode
    {
        if (! array_key_exists('work_hours', $data) || $data['work_hours'] === null || $data['work_hours'] === '') {
            $data['work_hours'] = $this->computeWorkHours(
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                (int) ($data['end_day_offset'] ?? 0),
            );
        }

        $before = $shiftCode?->toArray();
        $shiftCode ??= new ShiftCode;
        $shiftCode->fill($data);
        $shiftCode->save();

        $this->auditLogService->record($before ? 'shift_code.updated' : 'shift_code.created', $shiftCode, $before, $shiftCode->fresh()->toArray(), $performedBy);

        return $shiftCode;
    }

    public function seedDefaults(?string $performedBy = null, ?int $departmentId = null): void
    {
        $defaults = [
            ['code' => 'A', 'name' => 'Absent', 'is_work_shift' => false],
            ['code' => 'AM', 'name' => 'Morning Duty', 'start_time' => '07:00', 'end_time' => '15:00'],
            ['code' => 'M', 'name' => 'Mid Duty', 'start_time' => '08:00', 'end_time' => '16:00'],
            ['code' => 'N', 'name' => 'Night Duty', 'start_time' => '23:00', 'end_time' => '07:00', 'end_day_offset' => 1, 'is_night_shift' => true],
            ['code' => 'OB', 'name' => 'Official Business', 'is_work_shift' => false],
            ['code' => 'OT', 'name' => 'Overtime', 'is_work_shift' => true],
            ['code' => 'P', 'name' => 'Present', 'is_work_shift' => true],
            ['code' => 'PM', 'name' => 'Afternoon Duty', 'start_time' => '15:00', 'end_time' => '23:00'],
            ['code' => 'R', 'name' => 'Regular Shift', 'start_time' => '08:00', 'end_time' => '17:00', 'work_hours' => 8, 'is_work_shift' => true],
            ['code' => 'TD', 'name' => 'Training / Development', 'is_work_shift' => false],
            ['code' => 'CTO', 'name' => 'Compensatory Time Off', 'is_work_shift' => false, 'is_leave_code' => true],
            ['code' => 'O', 'name' => 'Off Duty', 'is_work_shift' => false],
            ['code' => 'RO', 'name' => 'Request Off', 'is_work_shift' => false],
            ['code' => 'ML', 'name' => 'Maternity Leave', 'is_work_shift' => false, 'is_leave_code' => true],
        ];

        foreach ($defaults as $row) {
            $shift = ShiftCode::firstOrNew(['department_id' => $departmentId, 'code' => $row['code']]);
            $before = $shift->exists ? $shift->toArray() : null;
            $shift->fill($row + ['is_active' => true]);
            $shift->save();
            $this->auditLogService->record($before ? 'shift_code.seed_updated' : 'shift_code.seed_created', $shift, $before, $shift->fresh()->toArray(), $performedBy);
        }
    }

    private function computeWorkHours(?string $startTime, ?string $endTime, int $endDayOffset): ?float
    {
        if (! $startTime || ! $endTime) {
            return null;
        }

        $start = strtotime('2000-01-01 '.$startTime);
        $end = strtotime('2000-01-01 '.$endTime.' +'.$endDayOffset.' day');

        if ($endDayOffset === 0 && $end < $start) {
            $end = strtotime('2000-01-02 '.$endTime);
        }

        if ($endDayOffset === 0 && substr($startTime, 0, 5) === '08:00' && substr($endTime, 0, 5) === '17:00') {
            return 8.0;
        }

        return round(max(0, ($end - $start) / 3600), 2);
    }
}
