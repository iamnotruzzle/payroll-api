<?php

namespace App\Services\Hris;

use App\Models\Hris\Eligibilities;
use App\Models\Hris\Employee as LegacyEmployee;
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
use App\Support\Hris\PdsFieldMaps;
use App\Models\HrisV2\Employee as V2Employee;
use App\Models\HrisV2\EmployeeCharacterReference;
use App\Models\HrisV2\EmployeeContact;
use App\Models\HrisV2\EmployeeDependent;
use App\Models\HrisV2\EmployeeEducation;
use App\Models\HrisV2\EmployeeEligibility;
use App\Models\HrisV2\EmployeeGovernmentId;
use App\Models\HrisV2\EmployeeOtherInfo;
use App\Models\HrisV2\EmployeePersonal;
use App\Models\HrisV2\EmployeeTraining;
use App\Models\HrisV2\EmployeeVoluntaryWork;
use App\Models\HrisV2\EmployeeWorkExperience;
use App\Models\HrisV2\EmploymentAssignment;
use App\Models\HrisV2\HrisMigrationRun;
use App\Models\HrisV2\LegacyRecordMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class EmployeeMigrationService
{
    /**
     * @return array{
     *     dry_run: bool,
     *     batch_key: string,
     *     source_employee_count: int,
     *     migrated_employee_count: int,
     *     source_section_count: int,
     *     migrated_section_count: int,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: list<string>
     * }
     */
    public function migrate(?string $batchKey = null, bool $dryRun = true, ?int $limit = null, ?string $empId = null): array
    {
        $batchKey ??= 'employees-'.now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        $errors = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $migrated = 0;
        $sourceSectionCount = $this->countSourceSections($limit, $empId);
        $migratedSectionCount = 0;

        $sourceQuery = LegacyEmployee::query()->orderBy('emp_id');

        if (filled($empId)) {
            $sourceQuery->where('emp_id', $empId);
        }

        $sourceCount = (clone $sourceQuery)->count();

        if ($limit !== null && ! filled($empId)) {
            $sourceQuery->limit($limit);
        }

        $run = null;

        if (! $dryRun) {
            $run = HrisMigrationRun::query()->create([
                'batch_key' => $batchKey,
                'status' => 'running',
                'source_employee_count' => $sourceCount,
                'source_section_count' => $sourceSectionCount,
                'started_at' => now(),
            ]);
        }

        $sourceQuery->chunkById(200, function ($employees) use ($dryRun, $run, &$created, &$updated, &$skipped, &$migrated, &$migratedSectionCount, &$errors) {
            foreach ($employees as $legacy) {
                try {
                    if (! filled($legacy->emp_id)) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $migrated++;
                        $migratedSectionCount += $this->countSectionsForEmpId((string) $legacy->emp_id);

                        continue;
                    }

                    $result = DB::connection('hris_v2')->transaction(function () use ($legacy, $run, &$migratedSectionCount) {
                        $status = $this->upsertEmployee($legacy, $run?->id);
                        $employee = V2Employee::query()->where('emp_id', $legacy->emp_id)->firstOrFail();
                        $migratedSectionCount += $this->upsertSections($employee, (string) $legacy->emp_id, $run?->id);

                        return $status;
                    });

                    if ($result === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }

                    $migrated++;
                } catch (Throwable $e) {
                    $skipped++;
                    $errors[] = "{$legacy->emp_id}: {$e->getMessage()}";
                }
            }
        }, 'emp_id');

        if ($run) {
            $run->update([
                'status' => empty($errors) ? 'completed' : 'completed_with_errors',
                'migrated_employee_count' => $migrated,
                'source_section_count' => $sourceSectionCount,
                'migrated_section_count' => $migratedSectionCount,
                'checksums' => [
                    'source_employee_count' => $sourceCount,
                    'source_section_count' => $sourceSectionCount,
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'migrated_section_count' => $migratedSectionCount,
                    'error_count' => count($errors),
                ],
                'notes' => empty($errors) ? null : implode("\n", array_slice($errors, 0, 50)),
                'finished_at' => now(),
            ]);
        }

        return [
            'dry_run' => $dryRun,
            'batch_key' => $batchKey,
            'source_employee_count' => $sourceCount,
            'migrated_employee_count' => $migrated,
            'source_section_count' => $sourceSectionCount,
            'migrated_section_count' => $migratedSectionCount,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function countSourceSections(?int $limit, ?string $empId = null): int
    {
        if (filled($empId)) {
            return $this->countSectionsForEmpId((string) $empId);
        }

        if ($limit === null) {
            return $this->countSectionsForEmpId(null);
        }

        $empIds = LegacyEmployee::query()->orderBy('emp_id')->limit($limit)->pluck('emp_id');

        return $empIds->sum(fn ($id) => $this->countSectionsForEmpId((string) $id));
    }

    private function countSectionsForEmpId(?string $empId): int
    {
        $tables = [
            LegacyDependent::query(),
            LegacyEducation::query(),
            LegacyEligibility::query(),
            LegacyWorkExperience::query(),
            LegacyTraining::query(),
            LegacyVoluntaryWork::query(),
            LegacyOtherInfo::query(),
            LegacyReference::query(),
        ];

        $total = 0;
        foreach ($tables as $query) {
            if ($empId !== null) {
                $query->where('emp_id', $empId);
            }
            $total += $query->count();
        }

        return $total;
    }

    private function upsertEmployee(LegacyEmployee $legacy, ?int $migrationRunId): string
    {
        $existing = V2Employee::query()->where('emp_id', $legacy->emp_id)->first();
        $wasCreated = $existing === null;

        $employee = V2Employee::query()->updateOrCreate(
            ['emp_id' => $legacy->emp_id],
            [
                'firstname' => $this->safeString($legacy->firstname, 255) ?? '',
                'middlename' => $this->safeString($legacy->middlename, 255),
                'lastname' => $this->safeString($legacy->lastname, 255) ?? '',
                'extension' => $this->safeString($legacy->extension, 64),
                'prefix' => $this->safeString($legacy->prefix, 64),
                'suffix' => $this->safeString($legacy->suffix, 255),
                'is_active' => $this->yesNoToBool($legacy->is_active, true),
                'is_external' => (bool) ($legacy->is_external ?? false),
                'date_hired' => $this->safeDate($legacy->getRawOriginal('date_hired') ?? $legacy->date_hired),
                'date_separated' => $this->safeDate($legacy->getRawOriginal('separationdate') ?? $legacy->separationdate),
                'separation_reason' => $this->safeString($legacy->separationtype, 255),
            ]
        );

        EmployeePersonal::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'birthdate' => $this->safeDate($legacy->birthdate),
                'birthplace' => $legacy->birthplace,
                'sex' => $legacy->gender,
                'civil_status' => $legacy->civil_stat,
                'citizenship' => $this->resolveCitizenshipLabel($legacy->citizenship_id),
                'religion' => $this->resolveReligionLabel($legacy->religion_id),
                'blood_type' => $legacy->blood_type,
                'height' => is_numeric($legacy->height) ? $legacy->height : null,
                'weight' => is_numeric($legacy->weight) ? $legacy->weight : null,
                'residential_address' => $this->formatAddress(
                    $legacy->house_no,
                    $legacy->street,
                    $legacy->subdiv,
                    $legacy->brgy
                ),
                'permanent_address' => $this->formatAddress(
                    $legacy->house_no2,
                    $legacy->street2,
                    $legacy->subdiv2,
                    $legacy->brgy2
                ),
                'is_related_third_degree' => $this->yesNoToBool($legacy->is_degree3),
                'is_related_fourth_degree' => $this->yesNoToBool($legacy->is_degree4),
                'is_admin_offense' => $this->yesNoToBool($legacy->is_adminoffense),
                'is_criminally_charged' => $this->yesNoToBool($legacy->is_criminallycharged),
                'is_convicted' => $this->yesNoToBool($legacy->is_convictedtocourt),
                'is_separated_service' => $this->yesNoToBool($legacy->is_separated),
                'is_election_candidate' => $this->yesNoToBool($legacy->is_candidate),
                'is_resigned_for_campaign' => $this->yesNoToBool($legacy->is_campaign),
                'is_immigrant' => $this->yesNoToBool($legacy->is_immigrant),
                'is_indigenous' => $this->yesNoToBool($legacy->is_indigenous),
                'is_pwd' => $this->yesNoToBool($legacy->is_pwd),
                'is_solo_parent' => $this->yesNoToBool($legacy->is_soloparent),
            ]
        );

        EmployeeGovernmentId::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'tin_no' => $legacy->tin_no,
                'gsis_no' => $legacy->gsis_no,
                'pagibig_no' => $legacy->pagibig_no,
                'phic_no' => $legacy->phic_no,
                'sss_no' => $legacy->sss_no,
                'issued_id_type' => $this->safeString($legacy->gov_id, 128),
                'issued_id_no' => $this->safeString($legacy->govid_no, 128),
                'issued_id_date_place' => $this->safeString($legacy->govid_dateplace, 255),
            ]
        );

        EmployeeContact::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'email' => $legacy->email,
                'mobile_no' => $legacy->mobile_no,
                'telephone_no' => $legacy->tel_no,
            ]
        );

        EmploymentAssignment::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'is_current' => true,
            ],
            [
                'department_id' => $legacy->department_id,
                'division_id' => null,
                'position_id' => $legacy->position_id,
                'employment_status_id' => $legacy->empstat_id,
                'step' => $legacy->step,
                'is_section_head' => $this->yesNoToBool($legacy->is_section_head, false),
                'effective_from' => $this->safeDate($legacy->date_hired),
                'effective_to' => null,
            ]
        );

        LegacyRecordMap::query()->updateOrCreate(
            [
                'source_table' => 'tbl_employee',
                'source_key' => (string) $legacy->emp_id,
                'target_table' => 'employees',
            ],
            [
                'target_id' => $employee->id,
                'emp_id' => $employee->emp_id,
                'checksum' => hash('sha256', json_encode([
                    $legacy->emp_id,
                    $legacy->firstname,
                    $legacy->lastname,
                    $legacy->is_active,
                    $legacy->department_id,
                    $legacy->position_id,
                ])),
                'migration_run_id' => $migrationRunId,
            ]
        );

        return $wasCreated ? 'created' : 'updated';
    }

    private function upsertSections(V2Employee $employee, string $empId, ?int $migrationRunId): int
    {
        $count = 0;

        foreach (LegacyDependent::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeDependent::query()->updateOrCreate(
                    ['legacy_dependent_id' => $row->dependent_id],
                    [
                        'employee_id' => $employee->id,
                        'firstname' => $this->safeString($row->firstname, 255) ?? '',
                        'middlename' => $this->safeString($row->middlename, 255),
                        'lastname' => $this->safeString($row->lastname, 255) ?? '',
                        'extension' => $this->safeString($row->extension, 32),
                        'relationship' => $this->safeString($row->relationship, 64),
                        'birthdate' => $this->safeDate($row->getRawOriginal('birthdate') ?? $row->birthdate),
                        'sex' => $this->safeString($row->gender, 16),
                        'occupation' => $this->safeString($row->occupation, 255),
                        'employer_name' => $this->safeString($row->emp_busname, 255),
                        'employer_address' => $this->safeString($row->emp_busadd, 1000),
                        'telephone_no' => $this->safeString($row->tel_no, 64),
                    ]
                );
                $this->mapLegacy('tbl_employee_dependents', (string) $row->dependent_id, 'employee_dependents', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyEducation::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeEducation::query()->updateOrCreate(
                    ['legacy_education_id' => $row->education_id],
                    [
                        'employee_id' => $employee->id,
                        'education_level' => $this->safeString($row->education_level, 64),
                        'education_title' => $this->safeString($row->education_title, 255),
                        'school' => $this->safeString($row->school, 255),
                        'start_date' => $this->safeDate($row->getRawOriginal('start_date') ?? $row->start_date),
                        'end_date' => $this->safeDate($row->getRawOriginal('end_date') ?? $row->end_date),
                        'units' => $row->units,
                        'year_graduated' => $this->safeString($row->year_graduated, 16),
                        'honors' => $this->safeString($row->honors, 255),
                        'url' => $this->safeString($row->url, 255),
                    ]
                );
                $this->mapLegacy('tbl_employee_education', (string) $row->education_id, 'employee_educations', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyEligibility::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $lookup = Eligibilities::query()->find($row->eligibility_title);
                $target = EmployeeEligibility::query()->updateOrCreate(
                    ['legacy_eligibility_id' => $row->eligibility_id],
                    [
                        'employee_id' => $employee->id,
                        'eligibility_lookup_id' => $row->eligibility_title,
                        'title' => $this->safeString($lookup?->e_title, 255),
                        'confer_date' => $this->safeDate($row->getRawOriginal('confer_date') ?? $row->confer_date),
                        'confer_place' => $this->safeString($row->confer_place, 255),
                        'rating' => $row->rating,
                        'license_no' => $this->safeString($row->license_no, 100),
                        'exp_date' => $this->safeDate($row->getRawOriginal('exp_date') ?? $row->exp_date),
                    ]
                );
                $this->mapLegacy('tbl_employee_eligibility', (string) $row->eligibility_id, 'employee_eligibilities', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyWorkExperience::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeWorkExperience::query()->updateOrCreate(
                    ['legacy_work_exp_id' => $row->work_exp_id],
                    [
                        'employee_id' => $employee->id,
                        'work_position' => $this->safeString($row->work_position, 255),
                        'work_status' => $this->resolveEmploymentStatusLabel($row->work_status),
                        'company_name' => $this->safeString($row->company_name, 255),
                        'company_address' => $this->safeString($row->company_address, 1000),
                        'salary' => $row->salary,
                        'salary_grade' => $this->safeString($row->sg, 32),
                        'step_inc' => $this->safeStepInc($row->step_inc),
                        'start_date' => $this->safeDate($row->getRawOriginal('start_date') ?? $row->start_date),
                        'end_date' => $this->safeDate($row->getRawOriginal('end_date') ?? $row->end_date),
                        'is_government' => $this->yesNoToBool($row->is_government),
                    ]
                );
                $this->mapLegacy('tbl_employee_work_exp', (string) $row->work_exp_id, 'employee_work_experiences', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyTraining::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $type = TrainingTypeLookup::query()->find($row->type);
                $target = EmployeeTraining::query()->updateOrCreate(
                    ['legacy_training_id' => $row->training_id],
                    [
                        'employee_id' => $employee->id,
                        'training_name' => $this->safeString($row->training_name, 5000),
                        'training_venue' => $this->safeString($row->training_venue, 2000),
                        'sponsor' => $this->safeString($row->sponsor, 2000),
                        'start_date' => $this->safeDate($row->getRawOriginal('start_date') ?? $row->start_date),
                        'end_date' => $this->safeDate($row->getRawOriginal('end_date') ?? $row->end_date),
                        'hours' => $row->hrs,
                        'type_id' => $row->type,
                        'type_name' => $this->safeString($type?->type, 255),
                        'url' => $this->safeString($row->url, 255),
                    ]
                );
                $this->mapLegacy('tbl_employee_training', (string) $row->training_id, 'employee_trainings', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyVoluntaryWork::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeVoluntaryWork::query()->updateOrCreate(
                    ['legacy_volwork_id' => $row->volwork_id],
                    [
                        'employee_id' => $employee->id,
                        'organization_name' => $this->safeString($row->volname, 255),
                        'start_date' => $this->safeDate($row->getRawOriginal('start_date') ?? $row->start_date),
                        'end_date' => $this->safeDate($row->getRawOriginal('end_date') ?? $row->end_date),
                        'hours' => $row->hrs,
                        'position' => $this->safeString($row->position, 255),
                    ]
                );
                $this->mapLegacy('tbl_employee_volwork', (string) $row->volwork_id, 'employee_voluntary_works', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyOtherInfo::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeOtherInfo::query()->updateOrCreate(
                    ['legacy_otherinfo_id' => $row->otherinfo_id],
                    [
                        'employee_id' => $employee->id,
                        'title' => $this->safeString($row->title, 255),
                        'type' => PdsFieldMaps::otherInfoTypeKey($row->type) ?: $this->safeString($row->type, 64),
                    ]
                );
                $this->mapLegacy('tbl_employee_otherinfo', (string) $row->otherinfo_id, 'employee_other_infos', $target->id, $empId, $migrationRunId);
            });
        }

        foreach (LegacyReference::query()->where('emp_id', $empId)->get() as $row) {
            $count += $this->upsertSectionRow(function () use ($row, $employee, $empId, $migrationRunId) {
                $target = EmployeeCharacterReference::query()->updateOrCreate(
                    ['legacy_reference_id' => $row->reference_id],
                    [
                        'employee_id' => $employee->id,
                        'name' => $this->safeString($row->ref_name, 255),
                        'address' => $this->safeString($row->ref_address, 255),
                        'telephone_no' => $this->safeString($row->ref_telno, 64),
                    ]
                );
                $this->mapLegacy('tbl_employee_ref', (string) $row->reference_id, 'employee_character_references', $target->id, $empId, $migrationRunId);
            });
        }

        return $count;
    }

    private function upsertSectionRow(callable $callback): int
    {
        try {
            $callback();

            return 1;
        } catch (Throwable) {
            return 0;
        }
    }

    private function mapLegacy(
        string $sourceTable,
        string $sourceKey,
        string $targetTable,
        int $targetId,
        string $empId,
        ?int $migrationRunId
    ): void {
        LegacyRecordMap::query()->updateOrCreate(
            [
                'source_table' => $sourceTable,
                'source_key' => $sourceKey,
                'target_table' => $targetTable,
            ],
            [
                'target_id' => $targetId,
                'emp_id' => $empId,
                'migration_run_id' => $migrationRunId,
            ]
        );
    }

    /**
     * Normalize legacy MySQL zero-dates / invalid values to null.
     */
    private function safeDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            $date = $value instanceof \DateTimeInterface
                ? \Carbon\Carbon::instance(\DateTimeImmutable::createFromInterface($value))
                : \Carbon\Carbon::parse(trim((string) $value));
        } catch (Throwable) {
            return null;
        }

        $year = (int) $date->format('Y');
        if ($year < 1900 || $year > 2100) {
            return null;
        }

        return $date->toDateString();
    }

    private function safeString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $maxLength);
    }

    private function safeStepInc(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        // Realistic salary-step range only; legacy sometimes stores dates/noise here.
        if ($int < -20 || $int > 50) {
            return null;
        }

        return $int;
    }

    private function yesNoToBool(mixed $value, bool $default = false): bool
    {
        return PdsFieldMaps::yesNoToBool($value, $default);
    }

    private function resolveEmploymentStatusLabel(mixed $workStatus): ?string
    {
        if ($workStatus === null || $workStatus === '') {
            return null;
        }

        if (is_numeric($workStatus)) {
            $label = EmploymentStatus::query()->find((int) $workStatus)?->status;
            if ($label) {
                return $this->safeString($label, 255);
            }
        }

        return $this->safeString($workStatus, 255);
    }

    private function resolveCitizenshipLabel(mixed $citizenshipId): ?string
    {
        if ($citizenshipId === null || $citizenshipId === '') {
            return null;
        }

        try {
            $label = DB::connection('hris')
                ->table('tbl_citizenship')
                ->where('citizenship_id', $citizenshipId)
                ->value('citizenship');

            return $this->safeString($label ?: (string) $citizenshipId, 255);
        } catch (Throwable) {
            return $this->safeString((string) $citizenshipId, 255);
        }
    }

    private function resolveReligionLabel(mixed $religionId): ?string
    {
        if ($religionId === null || $religionId === '') {
            return null;
        }

        try {
            $label = DB::connection('hris')
                ->table('tbl_religions')
                ->where('religion_id', $religionId)
                ->value('religion');

            return $this->safeString($label ?: (string) $religionId, 255);
        } catch (Throwable) {
            return $this->safeString((string) $religionId, 255);
        }
    }

    private function formatAddress(?string ...$parts): ?string
    {
        $line = collect($parts)->map(fn ($part) => trim((string) $part))->filter()->implode(', ');

        return $line !== '' ? $line : null;
    }
}
