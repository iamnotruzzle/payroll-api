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
        $before = $shiftCode?->toArray();
        $shiftCode ??= new ShiftCode();
        $shiftCode->fill($data);
        $shiftCode->save();

        $this->auditLogService->record($before ? 'shift_code.updated' : 'shift_code.created', $shiftCode, $before, $shiftCode->fresh()->toArray(), $performedBy);

        return $shiftCode;
    }

    public function seedDefaults(?string $performedBy = null): void
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
            ['code' => 'R', 'name' => 'Rest Day', 'is_work_shift' => false],
            ['code' => 'TD', 'name' => 'Training / Development', 'is_work_shift' => false],
            ['code' => 'CTO', 'name' => 'Compensatory Time Off', 'is_work_shift' => false, 'is_leave_code' => true],
            ['code' => 'H', 'name' => 'Holiday', 'is_work_shift' => false],
            ['code' => 'O', 'name' => 'Off Duty', 'is_work_shift' => false],
            ['code' => 'RO', 'name' => 'Request Off', 'is_work_shift' => false],
            ['code' => 'ML', 'name' => 'Maternity Leave', 'is_work_shift' => false, 'is_leave_code' => true],
        ];

        foreach ($defaults as $row) {
            $shift = ShiftCode::firstOrNew(['code' => $row['code']]);
            $before = $shift->exists ? $shift->toArray() : null;
            $shift->fill($row + ['is_active' => true]);
            $shift->save();
            $this->auditLogService->record($before ? 'shift_code.seed_updated' : 'shift_code.seed_created', $shift, $before, $shift->fresh()->toArray(), $performedBy);
        }
    }
}
