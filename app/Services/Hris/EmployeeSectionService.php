<?php

namespace App\Services\Hris;

use App\Models\Hris\Eligibilities;
use App\Models\Hris\EmployeeDependent;
use App\Models\Hris\EmployeeEducation;
use App\Models\Hris\EmployeeEligibility;
use App\Models\Hris\EmployeeLeave;
use App\Models\Hris\EmployeeOtherInfo;
use App\Models\Hris\EmployeeReference;
use App\Models\Hris\EmployeeTraining;
use App\Models\Hris\EmployeeVoluntaryWork;
use App\Models\Hris\EmployeeWorkExperience;
use App\Models\Hris\EligibilityLookup;
use App\Models\Hris\LeaveStatusLookup;
use App\Models\Hris\LeaveType;
use App\Models\Hris\TrainingTypeLookup;
use App\Models\Hris\EmployeeDtr;

class EmployeeSectionService
{
    public function dependents(string $empId): array
    {
        return EmployeeDependent::where('emp_id', $empId)
            ->orderBy('lastname')->orderBy('firstname')
            ->get()->toArray();
    }

    public function education(string $empId): array
    {
        return EmployeeEducation::where('emp_id', $empId)
            ->orderByDesc('education_level')
            ->orderByDesc('end_date')
            ->get()->toArray();
    }

    public function eligibilities(string $empId): array
    {
        return EmployeeEligibility::where('emp_id', $empId)
            ->orderByDesc('confer_date')
            ->get()
            ->map(function ($item) {
                $lookup = Eligibilities::find($item->eligibility_title);
                $item->title = $lookup?->e_title ?? "Eligibility #{$item->eligibility_title}";
                return $item;
            })->toArray();
    }

    public function workExperiences(string $empId): array
    {
        return EmployeeWorkExperience::where('emp_id', $empId)
            ->orderByDesc('start_date')
            ->get()->toArray();
    }

    public function trainings(string $empId): array
    {
        return EmployeeTraining::where('emp_id', $empId)
            ->orderByDesc('start_date')
            ->get()
            ->map(function ($item) {
                $type = TrainingTypeLookup::find($item->type);
                $item->type_name = $type?->type;
                return $item;
            })->toArray();
    }

    public function otherInfo(string $empId): array
    {
        return [
            'other_infos' => EmployeeOtherInfo::where('emp_id', $empId)
                ->orderBy('type')->orderBy('title')
                ->get()->toArray(),
            'voluntary_works' => EmployeeVoluntaryWork::where('emp_id', $empId)
                ->orderByDesc('start_date')
                ->get()->toArray(),
            'references' => EmployeeReference::where('emp_id', $empId)
                ->orderBy('ref_name')
                ->get()->toArray(),
        ];
    }

    public function leaves(string $empId): array
    {
        return EmployeeLeave::where('emp_id', $empId)
            ->orderByDesc('filing_date')
            ->get()
            ->map(function ($item) {
                $leaveType = LeaveType::find($item->leave_type);
                $status = LeaveStatusLookup::find($item->status ?? 0);
                $item->leave_name = $leaveType?->leave_name ?? "Leave #{$item->leave_type}";
                $item->status_name = $status?->status_name;
                return $item;
            })->toArray();
    }

    public function dtrs(string $empId, ?string $from = null, ?string $to = null): array
    {
        $query = EmployeeDtr::where('emp_id', $empId);

        if ($from) {
            $query->whereDate('dtr_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('dtr_date', '<=', $to);
        }

        if (! $from && ! $to) {
            $query->take(62);
        }

        return $query->orderByDesc('dtr_date')->get()->toArray();
    }
}
