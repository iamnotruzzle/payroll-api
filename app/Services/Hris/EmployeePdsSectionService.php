<?php

namespace App\Services\Hris;

use App\Models\Hris\Eligibilities;
use App\Models\Hris\EmployeeDependent as LegacyDependent;
use App\Models\Hris\EmployeeEducation as LegacyEducation;
use App\Models\Hris\EmployeeEligibility as LegacyEligibility;
use App\Models\Hris\EmployeeOtherInfo as LegacyOtherInfo;
use App\Models\Hris\EmployeeReference as LegacyReference;
use App\Models\Hris\EmployeeTraining as LegacyTraining;
use App\Models\Hris\EmployeeVoluntaryWork as LegacyVoluntaryWork;
use App\Models\Hris\EmployeeWorkExperience as LegacyWorkExperience;
use App\Models\Hris\EmploymentStatus;
use App\Models\Hris\TrainingTypeLookup;
use App\Models\HrisV2\Employee as V2Employee;
use App\Models\HrisV2\EmployeeCharacterReference as V2Reference;
use App\Models\HrisV2\EmployeeDependent as V2Dependent;
use App\Models\HrisV2\EmployeeEducation as V2Education;
use App\Models\HrisV2\EmployeeEligibility as V2Eligibility;
use App\Models\HrisV2\EmployeeOtherInfo as V2OtherInfo;
use App\Models\HrisV2\EmployeeTraining as V2Training;
use App\Models\HrisV2\EmployeeVoluntaryWork as V2VoluntaryWork;
use App\Models\HrisV2\EmployeeWorkExperience as V2WorkExperience;
use App\Support\Hris\EmployeeDirectoryQuery;
use App\Support\Hris\LegacyEmployeeMasterWriteGuard;
use App\Support\Hris\PdsFieldMaps;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class EmployeePdsSectionService
{
    public const SECTIONS = [
        'dependents',
        'educations',
        'eligibilities',
        'work_experiences',
        'trainings',
        'voluntary_works',
        'other_infos',
        'references',
    ];

    /**
     * @return Collection<int, object>
     */
    public function list(string $empId, string $section): Collection
    {
        $this->assertSection($section);

        return EmployeeDirectoryQuery::usesV2()
            ? $this->listV2($empId, $section)
            : $this->listLegacy($empId, $section);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(string $empId, string $section, array $data, ?int $recordId = null): object
    {
        $this->assertSection($section);

        return EmployeeDirectoryQuery::usesV2()
            ? $this->saveV2($empId, $section, $data, $recordId)
            : $this->saveLegacy($empId, $section, $data, $recordId);
    }

    public function delete(string $empId, string $section, int $recordId): void
    {
        $this->assertSection($section);

        if (EmployeeDirectoryQuery::usesV2()) {
            $this->deleteV2($empId, $section, $recordId);

            return;
        }

        $this->deleteLegacy($empId, $section, $recordId);
    }

    /**
     * @return Collection<int, object{id:int|string,label:string}>
     */
    public function eligibilityOptions(): Collection
    {
        return Eligibilities::query()
            ->orderBy('e_title')
            ->get(['e_id', 'e_title'])
            ->map(fn (Eligibilities $row) => (object) [
                'id' => $row->e_id,
                'label' => $row->e_title,
            ]);
    }

    /**
     * @return Collection<int, object{id:int|string,label:string}>
     */
    public function trainingTypeOptions(): Collection
    {
        return TrainingTypeLookup::query()
            ->orderBy('type')
            ->get()
            ->map(fn (TrainingTypeLookup $row) => (object) [
                'id' => $row->getKey(),
                'label' => $row->type,
            ]);
    }

    /**
     * @return Collection<int, object{id:int|string,label:string}>
     */
    public function employmentStatusOptions(): Collection
    {
        return EmploymentStatus::query()
            ->orderBy('empstat_id')
            ->get(['empstat_id', 'status'])
            ->map(fn (EmploymentStatus $row) => (object) [
                'id' => $row->empstat_id,
                'label' => $row->status,
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function educationLevelOptions(): array
    {
        return PdsFieldMaps::EDUCATION_LEVELS;
    }

    /**
     * @return array<string, string>
     */
    public function otherInfoTypeOptions(): array
    {
        return PdsFieldMaps::OTHER_INFO_TYPES;
    }

    private function assertSection(string $section): void
    {
        if (! in_array($section, self::SECTIONS, true)) {
            throw new InvalidArgumentException("Unknown PDS section [{$section}].");
        }
    }

    private function resolveV2Employee(string $empId): V2Employee
    {
        return V2Employee::query()->where('emp_id', $empId)->firstOrFail();
    }

    /**
     * @return Collection<int, object>
     */
    private function listLegacy(string $empId, string $section): Collection
    {
        return match ($section) {
            'dependents' => LegacyDependent::query()
                ->where('emp_id', $empId)
                ->orderBy('lastname')->orderBy('firstname')
                ->get()
                ->map(fn (LegacyDependent $row) => (object) [
                    'id' => (int) $row->dependent_id,
                    'firstname' => $row->firstname,
                    'middlename' => $row->middlename,
                    'lastname' => $row->lastname,
                    'extension' => $row->extension,
                    'relationship' => $row->relationship,
                    'birthdate' => optional($row->birthdate)->format('Y-m-d'),
                    'sex' => PdsFieldMaps::sexCode($row->gender) ?? '',
                    'sex_label' => PdsFieldMaps::sexLabel($row->gender),
                    'occupation' => $row->occupation,
                    'employer_name' => $row->emp_busname,
                    'employer_address' => $row->emp_busadd,
                    'telephone_no' => $row->tel_no,
                    'label' => trim(collect([$row->firstname, $row->lastname])->filter()->implode(' ')),
                ]),
            'educations' => LegacyEducation::query()
                ->where('emp_id', $empId)
                ->orderByDesc('education_level')
                ->orderByDesc('end_date')
                ->get()
                ->map(fn (LegacyEducation $row) => (object) [
                    'id' => (int) $row->education_id,
                    'education_level' => (string) $row->education_level,
                    'education_level_label' => PdsFieldMaps::educationLevelLabel($row->education_level),
                    'education_title' => $row->education_title,
                    'school' => $row->school,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'units' => $row->units,
                    'year_graduated' => $row->year_graduated,
                    'honors' => $row->honors,
                    'url' => $row->url,
                    'label' => $row->school ?: $row->education_title ?: 'Education',
                ]),
            'eligibilities' => LegacyEligibility::query()
                ->where('emp_id', $empId)
                ->orderByDesc('confer_date')
                ->get()
                ->map(function (LegacyEligibility $row) {
                    $lookup = Eligibilities::query()->find($row->eligibility_title);

                    return (object) [
                        'id' => (int) $row->eligibility_id,
                        'eligibility_lookup_id' => $row->eligibility_title,
                        'title' => $lookup?->e_title ?? "Eligibility #{$row->eligibility_title}",
                        'confer_date' => optional($row->confer_date)->format('Y-m-d'),
                        'confer_place' => $row->confer_place,
                        'rating' => $row->rating,
                        'license_no' => $row->license_no,
                        'exp_date' => optional($row->exp_date)->format('Y-m-d'),
                        'label' => $lookup?->e_title ?? "Eligibility #{$row->eligibility_title}",
                    ];
                }),
            'work_experiences' => LegacyWorkExperience::query()
                ->where('emp_id', $empId)
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (LegacyWorkExperience $row) => (object) [
                    'id' => (int) $row->work_exp_id,
                    'work_position' => $row->work_position,
                    'work_status_id' => $row->work_status,
                    'work_status' => $this->employmentStatusLabel($row->work_status),
                    'company_name' => $row->company_name,
                    'company_address' => $row->company_address,
                    'salary' => $row->salary,
                    'salary_grade' => $row->sg,
                    'step_inc' => $row->step_inc,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'is_government' => PdsFieldMaps::yesNoToBool($row->is_government),
                    'label' => $row->work_position ?: $row->company_name ?: 'Work experience',
                ]),
            'trainings' => LegacyTraining::query()
                ->where('emp_id', $empId)
                ->orderByDesc('start_date')
                ->get()
                ->map(function (LegacyTraining $row) {
                    $type = TrainingTypeLookup::query()->find($row->type);

                    return (object) [
                        'id' => (int) $row->training_id,
                        'training_name' => $row->training_name,
                        'training_venue' => $row->training_venue,
                        'sponsor' => $row->sponsor,
                        'start_date' => $row->start_date,
                        'end_date' => $row->end_date,
                        'hours' => $row->hrs,
                        'type_id' => $row->type,
                        'type_name' => $type?->type,
                        'url' => $row->url,
                        'label' => $row->training_name ?: 'Training',
                    ];
                }),
            'voluntary_works' => LegacyVoluntaryWork::query()
                ->where('emp_id', $empId)
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (LegacyVoluntaryWork $row) => (object) [
                    'id' => (int) $row->volwork_id,
                    'organization_name' => $row->volname,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'hours' => $row->hrs,
                    'position' => $row->position,
                    'label' => $row->volname ?: 'Voluntary work',
                ]),
            'other_infos' => LegacyOtherInfo::query()
                ->where('emp_id', $empId)
                ->orderBy('type')->orderBy('title')
                ->get()
                ->map(fn (LegacyOtherInfo $row) => (object) [
                    'id' => (int) $row->otherinfo_id,
                    'title' => $row->title,
                    'type' => PdsFieldMaps::otherInfoTypeKey($row->type),
                    'type_label' => PdsFieldMaps::otherInfoTypeLabel($row->type),
                    'label' => $row->title ?: PdsFieldMaps::otherInfoTypeLabel($row->type) ?: 'Other info',
                ]),
            'references' => LegacyReference::query()
                ->where('emp_id', $empId)
                ->orderBy('ref_name')
                ->get()
                ->map(fn (LegacyReference $row) => (object) [
                    'id' => (int) $row->reference_id,
                    'name' => $row->ref_name,
                    'address' => $row->ref_address,
                    'telephone_no' => $row->ref_telno,
                    'label' => $row->ref_name ?: 'Reference',
                ]),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, object>
     */
    private function listV2(string $empId, string $section): Collection
    {
        $employee = $this->resolveV2Employee($empId);

        return match ($section) {
            'dependents' => $employee->dependents()->orderBy('lastname')->orderBy('firstname')->get()
                ->map(fn (V2Dependent $row) => (object) [
                    'id' => $row->id,
                    'firstname' => $row->firstname,
                    'middlename' => $row->middlename,
                    'lastname' => $row->lastname,
                    'extension' => $row->extension,
                    'relationship' => $row->relationship,
                    'birthdate' => optional($row->birthdate)->format('Y-m-d'),
                    'sex' => PdsFieldMaps::sexCode($row->sex) ?? '',
                    'sex_label' => PdsFieldMaps::sexLabel($row->sex),
                    'occupation' => $row->occupation,
                    'employer_name' => $row->employer_name,
                    'employer_address' => $row->employer_address,
                    'telephone_no' => $row->telephone_no,
                    'label' => $row->full_name,
                ]),
            'educations' => $employee->educations()->orderByDesc('education_level')->orderByDesc('end_date')->get()
                ->map(fn (V2Education $row) => (object) [
                    'id' => $row->id,
                    'education_level' => (string) ($row->education_level ?? ''),
                    'education_level_label' => PdsFieldMaps::educationLevelLabel($row->education_level),
                    'education_title' => $row->education_title,
                    'school' => $row->school,
                    'start_date' => optional($row->start_date)->format('Y-m-d'),
                    'end_date' => optional($row->end_date)->format('Y-m-d'),
                    'units' => $row->units,
                    'year_graduated' => $row->year_graduated,
                    'honors' => $row->honors,
                    'url' => $row->url,
                    'label' => $row->school ?: $row->education_title ?: 'Education',
                ]),
            'eligibilities' => $employee->eligibilities()->orderByDesc('confer_date')->get()
                ->map(fn (V2Eligibility $row) => (object) [
                    'id' => $row->id,
                    'eligibility_lookup_id' => $row->eligibility_lookup_id,
                    'title' => $row->title,
                    'confer_date' => optional($row->confer_date)->format('Y-m-d'),
                    'confer_place' => $row->confer_place,
                    'rating' => $row->rating,
                    'license_no' => $row->license_no,
                    'exp_date' => optional($row->exp_date)->format('Y-m-d'),
                    'label' => $row->title ?: 'Eligibility',
                ]),
            'work_experiences' => $employee->workExperiences()->orderByDesc('start_date')->get()
                ->map(fn (V2WorkExperience $row) => (object) [
                    'id' => $row->id,
                    'work_position' => $row->work_position,
                    'work_status_id' => $this->employmentStatusId($row->work_status),
                    'work_status' => $this->employmentStatusLabel($row->work_status),
                    'company_name' => $row->company_name,
                    'company_address' => $row->company_address,
                    'salary' => $row->salary,
                    'salary_grade' => $row->salary_grade,
                    'step_inc' => $row->step_inc,
                    'start_date' => optional($row->start_date)->format('Y-m-d'),
                    'end_date' => optional($row->end_date)->format('Y-m-d'),
                    'is_government' => (bool) $row->is_government,
                    'label' => $row->work_position ?: $row->company_name ?: 'Work experience',
                ]),
            'trainings' => $employee->trainings()->orderByDesc('start_date')->get()
                ->map(fn (V2Training $row) => (object) [
                    'id' => $row->id,
                    'training_name' => $row->training_name,
                    'training_venue' => $row->training_venue,
                    'sponsor' => $row->sponsor,
                    'start_date' => optional($row->start_date)->format('Y-m-d'),
                    'end_date' => optional($row->end_date)->format('Y-m-d'),
                    'hours' => $row->hours,
                    'type_id' => $row->type_id,
                    'type_name' => $row->type_name,
                    'url' => $row->url,
                    'label' => $row->training_name ?: 'Training',
                ]),
            'voluntary_works' => $employee->voluntaryWorks()->orderByDesc('start_date')->get()
                ->map(fn (V2VoluntaryWork $row) => (object) [
                    'id' => $row->id,
                    'organization_name' => $row->organization_name,
                    'start_date' => optional($row->start_date)->format('Y-m-d'),
                    'end_date' => optional($row->end_date)->format('Y-m-d'),
                    'hours' => $row->hours,
                    'position' => $row->position,
                    'label' => $row->organization_name ?: 'Voluntary work',
                ]),
            'other_infos' => $employee->otherInfos()->orderBy('type')->orderBy('title')->get()
                ->map(fn (V2OtherInfo $row) => (object) [
                    'id' => $row->id,
                    'title' => $row->title,
                    'type' => PdsFieldMaps::otherInfoTypeKey($row->type),
                    'type_label' => PdsFieldMaps::otherInfoTypeLabel($row->type),
                    'label' => $row->title ?: PdsFieldMaps::otherInfoTypeLabel($row->type) ?: 'Other info',
                ]),
            'references' => $employee->characterReferences()->orderBy('name')->get()
                ->map(fn (V2Reference $row) => (object) [
                    'id' => $row->id,
                    'name' => $row->name,
                    'address' => $row->address,
                    'telephone_no' => $row->telephone_no,
                    'label' => $row->name ?: 'Reference',
                ]),
            default => collect(),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveLegacy(string $empId, string $section, array $data, ?int $recordId): object
    {
        LegacyEmployeeMasterWriteGuard::assertWritable("PDS section {$section}");

        $model = match ($section) {
            'dependents' => tap(
                $recordId
                    ? LegacyDependent::query()->where('emp_id', $empId)->where('dependent_id', $recordId)->firstOrFail()
                    : new LegacyDependent(['emp_id' => $empId]),
                function (LegacyDependent $row) use ($data) {
                    $row->fill([
                        'firstname' => $data['firstname'],
                        'middlename' => $data['middlename'] ?: null,
                        'lastname' => $data['lastname'],
                        'extension' => $data['extension'] ?: null,
                        'relationship' => $data['relationship'] ?: null,
                        'birthdate' => $data['birthdate'] ?: null,
                        'gender' => PdsFieldMaps::sexCode($data['sex'] ?? null),
                        'occupation' => $data['occupation'] ?: null,
                        'emp_busname' => $data['employer_name'] ?: null,
                        'emp_busadd' => $data['employer_address'] ?: null,
                        'tel_no' => $data['telephone_no'] ?: null,
                    ])->save();
                }
            ),
            'educations' => tap(
                $recordId
                    ? LegacyEducation::query()->where('emp_id', $empId)->where('education_id', $recordId)->firstOrFail()
                    : new LegacyEducation(['emp_id' => $empId]),
                function (LegacyEducation $row) use ($data) {
                    $row->fill([
                        'education_level' => $data['education_level'] ?: null,
                        'education_title' => $data['education_title'] ?: null,
                        'school' => $data['school'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'units' => $data['units'] !== '' && $data['units'] !== null ? $data['units'] : null,
                        'year_graduated' => $data['year_graduated'] ?: null,
                        'honors' => $data['honors'] ?: null,
                        'url' => $data['url'] ?: null,
                    ])->save();
                }
            ),
            'eligibilities' => tap(
                $recordId
                    ? LegacyEligibility::query()->where('emp_id', $empId)->where('eligibility_id', $recordId)->firstOrFail()
                    : new LegacyEligibility(['emp_id' => $empId]),
                function (LegacyEligibility $row) use ($data) {
                    $row->fill([
                        'eligibility_title' => $data['eligibility_lookup_id'] ?: null,
                        'confer_date' => $data['confer_date'] ?: null,
                        'confer_place' => $data['confer_place'] ?: null,
                        'rating' => $data['rating'] !== '' && $data['rating'] !== null ? $data['rating'] : null,
                        'license_no' => $data['license_no'] ?: null,
                        'exp_date' => $data['exp_date'] ?: null,
                    ])->save();
                }
            ),
            'work_experiences' => tap(
                $recordId
                    ? LegacyWorkExperience::query()->where('emp_id', $empId)->where('work_exp_id', $recordId)->firstOrFail()
                    : new LegacyWorkExperience(['emp_id' => $empId]),
                function (LegacyWorkExperience $row) use ($data) {
                    $row->fill([
                        'work_position' => $data['work_position'] ?: null,
                        'work_status' => $this->employmentStatusId($data['work_status_id'] ?? $data['work_status'] ?? null),
                        'company_name' => $data['company_name'] ?: null,
                        'company_address' => $data['company_address'] ?: null,
                        'salary' => $data['salary'] !== '' && $data['salary'] !== null ? $data['salary'] : null,
                        'sg' => $data['salary_grade'] ?: null,
                        'step_inc' => $data['step_inc'] !== '' && $data['step_inc'] !== null ? $data['step_inc'] : null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'is_government' => ! empty($data['is_government']) ? 'Y' : 'N',
                    ])->save();
                }
            ),
            'trainings' => tap(
                $recordId
                    ? LegacyTraining::query()->where('emp_id', $empId)->where('training_id', $recordId)->firstOrFail()
                    : new LegacyTraining(['emp_id' => $empId]),
                function (LegacyTraining $row) use ($data) {
                    $row->fill([
                        'training_name' => $data['training_name'] ?: null,
                        'training_venue' => $data['training_venue'] ?: null,
                        'sponsor' => $data['sponsor'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'hrs' => $data['hours'] !== '' && $data['hours'] !== null ? $data['hours'] : null,
                        'type' => $data['type_id'] ?: null,
                        'url' => $data['url'] ?: null,
                    ])->save();
                }
            ),
            'voluntary_works' => tap(
                $recordId
                    ? LegacyVoluntaryWork::query()->where('emp_id', $empId)->where('volwork_id', $recordId)->firstOrFail()
                    : new LegacyVoluntaryWork(['emp_id' => $empId]),
                function (LegacyVoluntaryWork $row) use ($data) {
                    $row->fill([
                        'volname' => $data['organization_name'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'hrs' => $data['hours'] !== '' && $data['hours'] !== null ? $data['hours'] : null,
                        'position' => $data['position'] ?: null,
                    ])->save();
                }
            ),
            'other_infos' => tap(
                $recordId
                    ? LegacyOtherInfo::query()->where('emp_id', $empId)->where('otherinfo_id', $recordId)->firstOrFail()
                    : new LegacyOtherInfo(['emp_id' => $empId]),
                function (LegacyOtherInfo $row) use ($data) {
                    $row->fill([
                        'title' => $data['title'] ?: null,
                        'type' => PdsFieldMaps::otherInfoLegacyType($data['type'] ?? null),
                    ])->save();
                }
            ),
            'references' => tap(
                $recordId
                    ? LegacyReference::query()->where('emp_id', $empId)->where('reference_id', $recordId)->firstOrFail()
                    : new LegacyReference(['emp_id' => $empId]),
                function (LegacyReference $row) use ($data) {
                    $row->fill([
                        'ref_name' => $data['name'] ?: null,
                        'ref_address' => $data['address'] ?: null,
                        'ref_telno' => $data['telephone_no'] ?: null,
                    ])->save();
                }
            ),
            default => throw new InvalidArgumentException("Unknown PDS section [{$section}]."),
        };

        return $this->listLegacy($empId, $section)->firstWhere('id', (int) (
            match ($section) {
                'dependents' => $model->dependent_id,
                'educations' => $model->education_id,
                'eligibilities' => $model->eligibility_id,
                'work_experiences' => $model->work_exp_id,
                'trainings' => $model->training_id,
                'voluntary_works' => $model->volwork_id,
                'other_infos' => $model->otherinfo_id,
                'references' => $model->reference_id,
                default => 0,
            }
        )) ?? (object) ['id' => 0];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveV2(string $empId, string $section, array $data, ?int $recordId): object
    {
        $employee = $this->resolveV2Employee($empId);

        $model = match ($section) {
            'dependents' => tap(
                $recordId
                    ? $employee->dependents()->whereKey($recordId)->firstOrFail()
                    : new V2Dependent(['employee_id' => $employee->id]),
                function (V2Dependent $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'firstname' => $data['firstname'],
                        'middlename' => $data['middlename'] ?: null,
                        'lastname' => $data['lastname'],
                        'extension' => $data['extension'] ?: null,
                        'relationship' => $data['relationship'] ?: null,
                        'birthdate' => $data['birthdate'] ?: null,
                        'sex' => PdsFieldMaps::sexCode($data['sex'] ?? null),
                        'occupation' => $data['occupation'] ?: null,
                        'employer_name' => $data['employer_name'] ?: null,
                        'employer_address' => $data['employer_address'] ?: null,
                        'telephone_no' => $data['telephone_no'] ?: null,
                    ])->save();
                }
            ),
            'educations' => tap(
                $recordId
                    ? $employee->educations()->whereKey($recordId)->firstOrFail()
                    : new V2Education(['employee_id' => $employee->id]),
                function (V2Education $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'education_level' => $data['education_level'] !== '' && $data['education_level'] !== null
                            ? (string) $data['education_level']
                            : null,
                        'education_title' => $data['education_title'] ?: null,
                        'school' => $data['school'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'units' => $data['units'] !== '' && $data['units'] !== null ? $data['units'] : null,
                        'year_graduated' => $data['year_graduated'] ?: null,
                        'honors' => $data['honors'] ?: null,
                        'url' => $data['url'] ?: null,
                    ])->save();
                }
            ),
            'eligibilities' => tap(
                $recordId
                    ? $employee->eligibilities()->whereKey($recordId)->firstOrFail()
                    : new V2Eligibility(['employee_id' => $employee->id]),
                function (V2Eligibility $row) use ($data, $employee) {
                    $lookupId = $data['eligibility_lookup_id'] ?: null;
                    $title = $data['title'] ?: null;
                    if ($lookupId && ! $title) {
                        $title = Eligibilities::query()->find($lookupId)?->e_title;
                    }

                    $row->fill([
                        'employee_id' => $employee->id,
                        'eligibility_lookup_id' => $lookupId,
                        'title' => $title,
                        'confer_date' => $data['confer_date'] ?: null,
                        'confer_place' => $data['confer_place'] ?: null,
                        'rating' => $data['rating'] !== '' && $data['rating'] !== null ? $data['rating'] : null,
                        'license_no' => $data['license_no'] ?: null,
                        'exp_date' => $data['exp_date'] ?: null,
                    ])->save();
                }
            ),
            'work_experiences' => tap(
                $recordId
                    ? $employee->workExperiences()->whereKey($recordId)->firstOrFail()
                    : new V2WorkExperience(['employee_id' => $employee->id]),
                function (V2WorkExperience $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'work_position' => $data['work_position'] ?: null,
                        'work_status' => $this->employmentStatusLabel($data['work_status_id'] ?? $data['work_status'] ?? null),
                        'company_name' => $data['company_name'] ?: null,
                        'company_address' => $data['company_address'] ?: null,
                        'salary' => $data['salary'] !== '' && $data['salary'] !== null ? $data['salary'] : null,
                        'salary_grade' => $data['salary_grade'] ?: null,
                        'step_inc' => $data['step_inc'] !== '' && $data['step_inc'] !== null ? $data['step_inc'] : null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'is_government' => ! empty($data['is_government']),
                    ])->save();
                }
            ),
            'trainings' => tap(
                $recordId
                    ? $employee->trainings()->whereKey($recordId)->firstOrFail()
                    : new V2Training(['employee_id' => $employee->id]),
                function (V2Training $row) use ($data, $employee) {
                    $typeId = $data['type_id'] ?: null;
                    $typeName = $data['type_name'] ?: null;
                    if ($typeId && ! $typeName) {
                        $typeName = TrainingTypeLookup::query()->find($typeId)?->type;
                    }

                    $row->fill([
                        'employee_id' => $employee->id,
                        'training_name' => $data['training_name'] ?: null,
                        'training_venue' => $data['training_venue'] ?: null,
                        'sponsor' => $data['sponsor'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'hours' => $data['hours'] !== '' && $data['hours'] !== null ? $data['hours'] : null,
                        'type_id' => $typeId,
                        'type_name' => $typeName,
                        'url' => $data['url'] ?: null,
                    ])->save();
                }
            ),
            'voluntary_works' => tap(
                $recordId
                    ? $employee->voluntaryWorks()->whereKey($recordId)->firstOrFail()
                    : new V2VoluntaryWork(['employee_id' => $employee->id]),
                function (V2VoluntaryWork $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'organization_name' => $data['organization_name'] ?: null,
                        'start_date' => $data['start_date'] ?: null,
                        'end_date' => $data['end_date'] ?: null,
                        'hours' => $data['hours'] !== '' && $data['hours'] !== null ? $data['hours'] : null,
                        'position' => $data['position'] ?: null,
                    ])->save();
                }
            ),
            'other_infos' => tap(
                $recordId
                    ? $employee->otherInfos()->whereKey($recordId)->firstOrFail()
                    : new V2OtherInfo(['employee_id' => $employee->id]),
                function (V2OtherInfo $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'title' => $data['title'] ?: null,
                        'type' => PdsFieldMaps::otherInfoTypeKey($data['type'] ?? null) ?: null,
                    ])->save();
                }
            ),
            'references' => tap(
                $recordId
                    ? $employee->characterReferences()->whereKey($recordId)->firstOrFail()
                    : new V2Reference(['employee_id' => $employee->id]),
                function (V2Reference $row) use ($data, $employee) {
                    $row->fill([
                        'employee_id' => $employee->id,
                        'name' => $data['name'] ?: null,
                        'address' => $data['address'] ?: null,
                        'telephone_no' => $data['telephone_no'] ?: null,
                    ])->save();
                }
            ),
            default => throw new InvalidArgumentException("Unknown PDS section [{$section}]."),
        };

        return $this->listV2($empId, $section)->firstWhere('id', $model->id) ?? (object) ['id' => $model->id];
    }

    private function deleteLegacy(string $empId, string $section, int $recordId): void
    {
        LegacyEmployeeMasterWriteGuard::assertWritable("PDS section {$section}");

        match ($section) {
            'dependents' => LegacyDependent::query()->where('emp_id', $empId)->where('dependent_id', $recordId)->delete(),
            'educations' => LegacyEducation::query()->where('emp_id', $empId)->where('education_id', $recordId)->delete(),
            'eligibilities' => LegacyEligibility::query()->where('emp_id', $empId)->where('eligibility_id', $recordId)->delete(),
            'work_experiences' => LegacyWorkExperience::query()->where('emp_id', $empId)->where('work_exp_id', $recordId)->delete(),
            'trainings' => LegacyTraining::query()->where('emp_id', $empId)->where('training_id', $recordId)->delete(),
            'voluntary_works' => LegacyVoluntaryWork::query()->where('emp_id', $empId)->where('volwork_id', $recordId)->delete(),
            'other_infos' => LegacyOtherInfo::query()->where('emp_id', $empId)->where('otherinfo_id', $recordId)->delete(),
            'references' => LegacyReference::query()->where('emp_id', $empId)->where('reference_id', $recordId)->delete(),
            default => null,
        };
    }

    private function deleteV2(string $empId, string $section, int $recordId): void
    {
        $employee = $this->resolveV2Employee($empId);

        match ($section) {
            'dependents' => $employee->dependents()->whereKey($recordId)->delete(),
            'educations' => $employee->educations()->whereKey($recordId)->delete(),
            'eligibilities' => $employee->eligibilities()->whereKey($recordId)->delete(),
            'work_experiences' => $employee->workExperiences()->whereKey($recordId)->delete(),
            'trainings' => $employee->trainings()->whereKey($recordId)->delete(),
            'voluntary_works' => $employee->voluntaryWorks()->whereKey($recordId)->delete(),
            'other_infos' => $employee->otherInfos()->whereKey($recordId)->delete(),
            'references' => $employee->characterReferences()->whereKey($recordId)->delete(),
            default => null,
        };
    }

    private function employmentStatusLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return EmploymentStatus::query()->find((int) $value)?->status
                ?? (string) $value;
        }

        return (string) $value;
    }

    private function employmentStatusId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $match = EmploymentStatus::query()
            ->whereRaw('LOWER(status) = ?', [strtolower(trim((string) $value))])
            ->first();

        return $match?->empstat_id;
    }
}
