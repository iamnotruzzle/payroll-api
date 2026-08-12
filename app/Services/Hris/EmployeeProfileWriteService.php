<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\UserAccount;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeProfileWriteService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{employee: Employee, temporary_password: ?string}
     */
    public function createEmployee(array $data, bool $provisionAccount = true): array
    {
        $empId = $this->normalizeEmpId((string) $data['emp_id']);

        if (Employee::query()->where('emp_id', $empId)->exists()) {
            throw ValidationException::withMessages([
                'emp_id' => 'Employee number is already in use.',
            ]);
        }

        return DB::connection('hris')->transaction(function () use ($data, $empId, $provisionAccount) {
            $employee = new Employee;
            $employee->emp_id = $empId;
            $employee->fill($this->coreAttributes($data) + [
                'position_id' => $data['position_id'] ?: null,
                'department_id' => $data['department_id'] ?: null,
                'empstat_id' => $data['empstat_id'] ?: null,
                'is_active' => 'Y',
            ]);
            $employee->save();

            app(EmploymentHistoryService::class)->seedFromEmployeeIfEmpty(
                $employee,
                auth()->user()?->emp_id
            );

            $temporaryPassword = null;
            if ($provisionAccount && ! UserAccount::query()->where('emp_id', $empId)->exists()) {
                $temporaryPassword = $this->provisionDefaultAccount(
                    $empId,
                    auth()->user()?->emp_id
                );
            }

            return [
                'employee' => $employee->fresh(['department', 'position', 'employmentStatus']),
                'temporary_password' => $temporaryPassword,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCoreProfile(string $empId, array $data): Employee
    {
        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();

        $employee->fill($this->coreAttributes($data));
        $employee->save();

        return $employee->fresh(['department', 'position']);
    }

    /**
     * @param  array{date_separated?:?string,separation_reason?:?string}  $meta
     */
    public function setActive(string $empId, bool $isActive, array $meta = []): Employee
    {
        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();
        $employee->is_active = $isActive ? 'Y' : 'N';

        if (! $isActive) {
            if (! empty($meta['date_separated'])) {
                $employee->separationdate = $meta['date_separated'];
            }
            if (! empty($meta['separation_reason'])) {
                $employee->separationtype = $meta['separation_reason'];
            }
        }

        $employee->save();

        return $employee->fresh(['department', 'position']);
    }

    public function clearLoginAttempt(string $empId): void
    {
        UserAccount::query()
            ->where('emp_id', $empId)
            ->where(function ($query) {
                $query->whereNull('login_attempt')->orWhere('login_attempt', 0);
            })
            ->update(['login_attempt' => 1]);
    }

    public function provisionDefaultAccount(string $empId, ?string $createdByEmpId = null): string
    {
        $temporary = 'ChangeMe'.random_int(1000, 9999).'!';

        $account = UserAccount::query()->create([
            'emp_id' => $empId,
            'username' => $empId,
            'password' => $temporary,
            'login_attempt' => 0,
            'user_level' => 4,
            'created_by' => is_numeric($createdByEmpId) ? (int) $createdByEmpId : null,
        ]);

        if (Role::query()->where('name', 'employee')->where('guard_name', 'web')->exists()) {
            $account->assignRole('employee');
        }

        return $temporary;
    }

    public function normalizeEmpId(string $empId): string
    {
        $trimmed = trim($empId);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (ctype_digit($trimmed) && strlen($trimmed) < 6) {
            return str_pad($trimmed, 6, '0', STR_PAD_LEFT);
        }

        return $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function coreAttributes(array $data): array
    {
        return [
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'] ?: null,
            'lastname' => $data['lastname'],
            'extension' => $data['extension'] ?: null,
            'prefix' => $data['prefix'] ?: null,
            'suffix' => $data['suffix'] ?: null,
            'date_hired' => $data['date_hired'] ?: null,
            'email' => $data['email'] ?: null,
            'mobile_no' => $data['mobile_no'] ?: null,
            'tel_no' => $data['telephone_no'] ?: null,
            'birthdate' => $data['birthdate'] ?: null,
            'birthplace' => $data['birthplace'] ?: null,
            'gender' => $data['sex'] ?: null,
            'civil_stat' => $data['civil_status'] ?: null,
            'blood_type' => $data['blood_type'] ?: null,
            'citizenship_id' => $data['citizenship'] ?: null,
            'religion_id' => $data['religion'] ?: null,
            'height' => $data['height'] !== '' && $data['height'] !== null ? $data['height'] : null,
            'weight' => $data['weight'] !== '' && $data['weight'] !== null ? $data['weight'] : null,
            'tin_no' => $data['tin_no'] ?: null,
            'gsis_no' => $data['gsis_no'] ?: null,
            'pagibig_no' => $data['pagibig_no'] ?: null,
            'phic_no' => $data['phic_no'] ?: null,
            'sss_no' => $data['sss_no'] ?: null,
            'gov_id' => $data['issued_id_type'] ?: null,
            'govid_no' => $data['issued_id_no'] ?: null,
            'govid_dateplace' => $data['issued_id_date_place'] ?: null,
            'is_degree3' => $this->ynOrNull($data['is_related_third_degree'] ?? null),
            'is_degree4' => $this->ynOrNull($data['is_related_fourth_degree'] ?? null),
            'is_adminoffense' => $this->ynOrNull($data['is_admin_offense'] ?? null),
            'is_criminallycharged' => $this->ynOrNull($data['is_criminally_charged'] ?? null),
            'is_convictedtocourt' => $this->ynOrNull($data['is_convicted'] ?? null),
            'is_separated' => $this->ynOrNull($data['is_separated_service'] ?? null),
            'is_candidate' => $this->ynOrNull($data['is_election_candidate'] ?? null),
            'is_campaign' => $this->ynOrNull($data['is_resigned_for_campaign'] ?? null),
            'is_immigrant' => $this->ynOrNull($data['is_immigrant'] ?? null),
            'is_indigenous' => $this->ynOrNull($data['is_indigenous'] ?? null),
            'is_pwd' => $this->ynOrNull($data['is_pwd'] ?? null),
            'is_soloparent' => $this->ynOrNull($data['is_solo_parent'] ?? null),
        ];
    }

    private function ynOrNull(mixed $value): ?string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['Y', '1', 'TRUE', 'YES'], true) ? 'Y' : 'N';
    }
}
