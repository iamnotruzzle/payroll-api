<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;

class EmployeeProfileWriteService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCoreProfile(string $empId, array $data): Employee
    {
        $employee = Employee::query()->where('emp_id', $empId)->firstOrFail();

        $employee->fill([
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
            'citizenship' => $data['citizenship'] ?: null,
            'religion' => $data['religion'] ?: null,
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
        ]);

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
        $employee->save();

        return $employee->fresh(['department', 'position']);
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
