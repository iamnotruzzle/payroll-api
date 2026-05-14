<?php

namespace App\Services\Schedule;

use App\Models\Schedule\ScheduleTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleTemplateService
{
    public function __construct(private AuditLogService $auditLogService) {}

    public function save(array $data, ?string $performedBy = null): ScheduleTemplate
    {
        return DB::connection('payroll_scheduler')->transaction(function () use ($data, $performedBy) {
            $existing = isset($data['id']) ? ScheduleTemplate::find($data['id']) : null;
            $duplicate = ScheduleTemplate::query()
                ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
                ->where('department_id', $data['department_id'] ?? null)
                ->where('rotation_group_id', $data['rotation_group_id'] ?? null)
                ->where('name', $data['name'])
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'A schedule template with this name already exists for this department and rotation group.',
                ]);
            }

            $before = $existing?->load('days.shiftCode')->toArray();
            $template = ScheduleTemplate::updateOrCreate(
                ['id' => $data['id'] ?? null],
                collect($data)->only(['name', 'department_id', 'rotation_group_id', 'is_active'])->all()
            );

            if (array_key_exists('days', $data)) {
                $template->days()->delete();
                foreach ($data['days'] as $index => $shiftCodeId) {
                    $template->days()->create([
                        'day_sequence' => $index + 1,
                        'shift_code_id' => $shiftCodeId,
                    ]);
                }
            }

            $fresh = $template->fresh('days.shiftCode');
            $this->auditLogService->record(
                $before ? 'schedule_template.updated' : 'schedule_template.created',
                $fresh,
                $before,
                $fresh->toArray(),
                $performedBy,
            );

            return $fresh;
        });
    }
}
