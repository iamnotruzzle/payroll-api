<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee as LegacyEmployee;
use App\Models\HrisV2\Employee as V2Employee;
use App\Support\Hris\EmployeeDirectoryQuery;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeeProfileWriteService
{
    /**
     * @param  array{
     *     firstname:string,
     *     middlename:?string,
     *     lastname:string,
     *     extension:?string,
     *     prefix:?string,
     *     suffix:?string,
     *     date_hired:?string,
     *     email:?string,
     *     mobile_no:?string,
     *     telephone_no:?string,
     *     emergency_contact_name:?string,
     *     emergency_contact_no:?string,
     *     birthdate:?string,
     *     birthplace:?string,
     *     sex:?string,
     *     civil_status:?string,
     *     blood_type:?string,
     *     residential_address:?string,
     *     permanent_address:?string,
     *     tin_no:?string,
     *     gsis_no:?string,
     *     pagibig_no:?string,
     *     phic_no:?string,
     *     sss_no:?string
     * }  $data
     */
    public function updateCoreProfile(string $empId, array $data): LegacyEmployee|V2Employee
    {
        return EmployeeDirectoryQuery::usesV2()
            ? $this->updateV2($empId, $data)
            : $this->updateLegacy($empId, $data);
    }

    /**
     * @param  array{date_separated?:?string,separation_reason?:?string}  $meta
     */
    public function setActive(string $empId, bool $isActive, array $meta = []): LegacyEmployee|V2Employee
    {
        return EmployeeDirectoryQuery::usesV2()
            ? $this->setActiveV2($empId, $isActive, $meta)
            : $this->setActiveLegacy($empId, $isActive);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateLegacy(string $empId, array $data): LegacyEmployee
    {
        $employee = LegacyEmployee::query()->where('emp_id', $empId)->firstOrFail();

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
     * @param  array<string, mixed>  $data
     */
    private function updateV2(string $empId, array $data): V2Employee
    {
        return DB::connection('hris_v2')->transaction(function () use ($empId, $data) {
            $employee = V2Employee::query()->where('emp_id', $empId)->firstOrFail();

            $employee->fill([
                'firstname' => $data['firstname'],
                'middlename' => $data['middlename'] ?: null,
                'lastname' => $data['lastname'],
                'extension' => $data['extension'] ?: null,
                'prefix' => $data['prefix'] ?: null,
                'suffix' => $data['suffix'] ?: null,
                'date_hired' => $data['date_hired'] ?: null,
            ])->save();

            $employee->contact()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'email' => $data['email'] ?: null,
                    'mobile_no' => $data['mobile_no'] ?: null,
                    'telephone_no' => $data['telephone_no'] ?: null,
                    'emergency_contact_name' => $data['emergency_contact_name'] ?: null,
                    'emergency_contact_no' => $data['emergency_contact_no'] ?: null,
                ]
            );

            $employee->personal()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'birthdate' => $data['birthdate'] ?: null,
                    'birthplace' => $data['birthplace'] ?: null,
                    'sex' => $data['sex'] ?: null,
                    'civil_status' => $data['civil_status'] ?: null,
                    'blood_type' => $data['blood_type'] ?: null,
                    'citizenship' => $data['citizenship'] ?: null,
                    'religion' => $data['religion'] ?: null,
                    'height' => $data['height'] !== '' && $data['height'] !== null ? $data['height'] : null,
                    'weight' => $data['weight'] !== '' && $data['weight'] !== null ? $data['weight'] : null,
                    'residential_address' => $data['residential_address'] ?: null,
                    'permanent_address' => $data['permanent_address'] ?: null,
                    'is_related_third_degree' => $this->ynToBool($data['is_related_third_degree'] ?? null),
                    'is_related_fourth_degree' => $this->ynToBool($data['is_related_fourth_degree'] ?? null),
                    'is_admin_offense' => $this->ynToBool($data['is_admin_offense'] ?? null),
                    'is_criminally_charged' => $this->ynToBool($data['is_criminally_charged'] ?? null),
                    'is_convicted' => $this->ynToBool($data['is_convicted'] ?? null),
                    'is_separated_service' => $this->ynToBool($data['is_separated_service'] ?? null),
                    'is_election_candidate' => $this->ynToBool($data['is_election_candidate'] ?? null),
                    'is_resigned_for_campaign' => $this->ynToBool($data['is_resigned_for_campaign'] ?? null),
                    'is_immigrant' => $this->ynToBool($data['is_immigrant'] ?? null),
                    'is_indigenous' => $this->ynToBool($data['is_indigenous'] ?? null),
                    'is_pwd' => $this->ynToBool($data['is_pwd'] ?? null),
                    'is_solo_parent' => $this->ynToBool($data['is_solo_parent'] ?? null),
                ]
            );

            $employee->governmentIds()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'tin_no' => $data['tin_no'] ?: null,
                    'gsis_no' => $data['gsis_no'] ?: null,
                    'pagibig_no' => $data['pagibig_no'] ?: null,
                    'phic_no' => $data['phic_no'] ?: null,
                    'sss_no' => $data['sss_no'] ?: null,
                    'issued_id_type' => $data['issued_id_type'] ?: null,
                    'issued_id_no' => $data['issued_id_no'] ?: null,
                    'issued_id_date_place' => $data['issued_id_date_place'] ?: null,
                ]
            );

            return $employee->fresh(['personal', 'governmentIds', 'contact', 'currentAssignment']);
        });
    }

    private function setActiveLegacy(string $empId, bool $isActive): LegacyEmployee
    {
        $employee = LegacyEmployee::query()->where('emp_id', $empId)->firstOrFail();
        $employee->is_active = $isActive ? 'Y' : 'N';
        $employee->save();

        return $employee->fresh(['department', 'position']);
    }

    /**
     * @param  array{date_separated?:?string,separation_reason?:?string}  $meta
     */
    private function setActiveV2(string $empId, bool $isActive, array $meta): V2Employee
    {
        $employee = V2Employee::query()->where('emp_id', $empId)->firstOrFail();

        if ($isActive) {
            $employee->fill([
                'is_active' => true,
                'date_separated' => null,
                'separation_reason' => null,
            ])->save();
        } else {
            $reason = trim((string) ($meta['separation_reason'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException('Separation reason is required when deactivating.');
            }

            $employee->fill([
                'is_active' => false,
                'date_separated' => $meta['date_separated'] ?: now()->toDateString(),
                'separation_reason' => $reason,
            ])->save();
        }

        return $employee->fresh(['personal', 'governmentIds', 'contact', 'currentAssignment']);
    }

    private function ynToBool(mixed $value): ?bool
    {
        $raw = strtoupper(trim((string) ($value ?? '')));
        if ($raw === '') {
            return null;
        }

        return in_array($raw, ['Y', '1', 'TRUE', 'YES'], true);
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
