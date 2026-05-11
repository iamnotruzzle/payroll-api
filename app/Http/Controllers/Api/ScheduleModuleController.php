<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule\EmployeeScheduleSetting;
use App\Models\Schedule\MonthlySchedule;
use App\Models\Schedule\RotationGroup;
use App\Models\Schedule\ScheduleTemplate;
use App\Models\Schedule\StaffingRequirement;
use App\Services\Schedule\EmployeeReferenceService;
use App\Services\Schedule\RotationGroupService;
use App\Services\Schedule\ScheduleApprovalService;
use App\Services\Schedule\ScheduleConflictValidator;
use App\Services\Schedule\ScheduleDraftGenerationService;
use App\Services\Schedule\ScheduleLockService;
use App\Services\Schedule\ScheduleTemplateService;
use App\Services\Schedule\StaffingRequirementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleModuleController extends Controller
{
    public function syncEmployees(EmployeeReferenceService $service): JsonResponse
    {
        return response()->json(['synced' => $service->syncActiveEmployees()]);
    }

    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'string', 'max:50'],
            'default_shift_code_id' => ['nullable', 'exists:payroll_scheduler.shift_codes,id'],
            'can_rotate_shift' => ['boolean'],
            'max_consecutive_duty_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'max_night_shifts_per_month' => ['nullable', 'integer', 'min:0', 'max:31'],
            'is_active' => ['boolean'],
        ]);

        $setting = EmployeeScheduleSetting::updateOrCreate(['employee_id' => $data['employee_id']], $data);

        return response()->json($setting);
    }

    public function rotationGroups(): JsonResponse
    {
        return response()->json(RotationGroup::with('members')->orderBy('name')->paginate(20));
    }

    public function saveRotationGroup(Request $request, RotationGroupService $service): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:payroll_scheduler.rotation_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'members' => ['array'],
            'members.*' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($service->save($data));
    }

    public function staffingRequirements(): JsonResponse
    {
        return response()->json(StaffingRequirement::with('shiftCode')->orderBy('department_id')->paginate(20));
    }

    public function saveStaffingRequirement(Request $request, StaffingRequirementService $service): JsonResponse
    {
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'shift_code_id' => ['required', 'exists:payroll_scheduler.shift_codes,id'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'minimum_staff' => ['required', 'integer', 'min:1', 'max:999'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ]);

        $requirement = isset($data['id']) ? StaffingRequirement::find($data['id']) : null;

        return response()->json($service->save($data, $requirement));
    }

    public function templates(): JsonResponse
    {
        return response()->json(ScheduleTemplate::with('days.shiftCode', 'rotationGroup')->orderBy('name')->paginate(20));
    }

    public function saveTemplate(Request $request, ScheduleTemplateService $service): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:payroll_scheduler.schedule_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer'],
            'rotation_group_id' => ['nullable', 'exists:payroll_scheduler.rotation_groups,id'],
            'is_active' => ['boolean'],
            'days' => ['array'],
            'days.*' => ['required', 'exists:payroll_scheduler.shift_codes,id'],
        ]);

        return response()->json($service->save($data));
    }

    public function generateDraft(Request $request, ScheduleDraftGenerationService $service): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'department_id' => ['nullable', 'integer'],
            'schedule_template_id' => ['nullable', 'exists:payroll_scheduler.schedule_templates,id'],
        ]);

        return response()->json($service->generate(
            $data['year'],
            $data['month'],
            $data['department_id'] ?? null,
            $data['schedule_template_id'] ?? null,
            $request->user()?->getAuthIdentifier()
        ));
    }

    public function showSchedule(MonthlySchedule $schedule): JsonResponse
    {
        return response()->json($schedule->load('assignments.shiftCode'));
    }

    public function conflicts(MonthlySchedule $schedule, ScheduleConflictValidator $validator): JsonResponse
    {
        return response()->json(['conflicts' => $validator->validate($schedule)]);
    }

    public function review(Request $request, MonthlySchedule $schedule, ScheduleApprovalService $service): JsonResponse
    {
        return response()->json($service->review($schedule, $request->user()?->getAuthIdentifier()));
    }

    public function approve(Request $request, MonthlySchedule $schedule, ScheduleApprovalService $service): JsonResponse
    {
        return response()->json($service->approve($schedule, $request->user()?->getAuthIdentifier()));
    }

    public function lock(Request $request, MonthlySchedule $schedule, ScheduleLockService $service): JsonResponse
    {
        return response()->json($service->lock($schedule, $request->user()?->getAuthIdentifier()));
    }
}
