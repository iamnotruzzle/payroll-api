<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Hris\EmployeeDtr;
use App\Models\Hris\UserAccount;

abstract class MobileController extends Controller
{
    protected function empId(): string
    {
        $empId = (string) (auth()->user()?->emp_id ?? '');
        abort_if($empId === '', 403, 'No employee is linked to this account.');

        return $empId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(UserAccount $user): array
    {
        $user->loadMissing(['employee.department', 'employee.position']);
        $employee = $user->employee;

        return [
            'emp_id' => $user->emp_id,
            'username' => $user->username,
            'firstname' => $employee?->firstname,
            'lastname' => $employee?->lastname,
            'full_name' => $employee?->full_name,
            'position' => $employee?->position?->position_title,
            'department' => $employee?->department?->department,
            'must_update_profile' => (int) ($user->login_attempt ?? 1) === 0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function dtrPayload(?EmployeeDtr $dtr): ?array
    {
        if (! $dtr) {
            return null;
        }

        return [
            'dtr_id' => $dtr->dtr_id,
            'dtr_date' => optional($dtr->dtr_date)?->toDateString(),
            'time_in' => $dtr->timein_am,
            'time_out' => $dtr->timeout_pm,
            'timeout_nextday' => optional($dtr->timeout_nextday)?->toDateTimeString(),
        ];
    }
}
