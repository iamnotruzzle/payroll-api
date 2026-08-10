<?php

namespace App\Support\Hris;

use App\Services\Hris\EmployeePdsSectionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PdsPrintPresenter
{
    /** Official C1–C4 fixed slots (Excel Revised 2026). */
    public const CHILD_SLOTS = 12;

    public const ELIG_SLOTS = 7;

    public const WORK_SLOTS = 24;

    public const TRAIN_SLOTS = 17;

    public const VOL_SLOTS = 9;

    public const OTHER_SLOTS = 7;

    public const REF_SLOTS = 3;

    public function __construct(
        private readonly EmployeePdsSectionService $sections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(string $empId, ?string $backRoute = null): array
    {
        $employee = EmployeeDirectoryQuery::findForProfile($empId);
        abort_unless($employee, 404);

        $usesV2 = EmployeeDirectoryQuery::usesV2();
        $dependents = $this->sections->list($empId, 'dependents');
        $educations = $this->sections->list($empId, 'educations');
        $eligibilities = $this->sections->list($empId, 'eligibilities');
        $work = $this->sections->list($empId, 'work_experiences');
        $trainings = $this->sections->list($empId, 'trainings');
        $voluntary = $this->sections->list($empId, 'voluntary_works');
        $otherInfos = $this->sections->list($empId, 'other_infos');
        $references = $this->sections->list($empId, 'references');

        $spouse = $this->firstByRelationship($dependents, ['SPOUSE', 'WIFE', 'HUSBAND']);
        $father = $this->firstByRelationship($dependents, ['FATHER']);
        $mother = $this->firstByRelationship($dependents, ['MOTHER']);
        $children = $dependents->filter(function ($row) {
            $rel = strtoupper(trim((string) ($row->relationship ?? '')));

            return $rel === 'CHILD' || $rel === 'CHILDREN' || str_contains($rel, 'SON') || str_contains($rel, 'DAUGHTER');
        })->values()->map(fn ($row) => [
            'name' => $this->fullName($row),
            'birthdate' => $this->dmy($row->birthdate ?? null),
        ])->all();

        $eligMapped = $eligibilities->map(fn ($row) => [
            'title' => $this->na($row->title ?? null),
            'rating' => $this->na($row->rating ?? null),
            'confer_date' => $this->dmy($row->confer_date ?? null),
            'confer_place' => $this->na($row->confer_place ?? null),
            'license_no' => $this->na($row->license_no ?? null),
            'exp_date' => $this->dmy($row->exp_date ?? null),
        ])->all();

        $workMapped = $work->map(fn ($row) => [
            'from' => $this->dmy($row->start_date ?? null),
            'to' => $this->dmy($row->end_date ?? null),
            'position' => $this->na($row->work_position ?? null),
            'company' => $this->na($row->company_name ?? null),
            'salary' => $this->na($row->salary ?? null),
            'grade_step' => $this->gradeStep($row->salary_grade ?? null, $row->step_inc ?? null),
            'status' => $this->na($row->work_status ?? null),
            'govt' => ! empty($row->is_government) ? 'Y' : 'N',
        ])->all();

        $trainMapped = $trainings->map(fn ($row) => [
            'title' => $this->na($row->training_name ?? null),
            'from' => $this->dmy($row->start_date ?? null),
            'to' => $this->dmy($row->end_date ?? null),
            'hours' => $this->na($row->hours ?? null),
            'type' => $this->na($row->type_name ?? null),
            'sponsor' => $this->na($row->sponsor ?? null),
        ])->all();

        $volMapped = $voluntary->map(fn ($row) => [
            'org' => $this->na($row->organization_name ?? null),
            'from' => $this->dmy($row->start_date ?? null),
            'to' => $this->dmy($row->end_date ?? null),
            'hours' => $this->na($row->hours ?? null),
            'position' => $this->na($row->position ?? null),
        ])->all();

        $skills = $this->otherByType($otherInfos, ['skill', 'skills', 'hobby', 'hobbies', 'special skills', '0']);
        $recognitions = $this->otherByType($otherInfos, ['recognition', 'award', 'distinction', 'non-academic', '1']);
        $memberships = $this->otherByType($otherInfos, ['membership', 'association', 'organization', 'org', '2']);
        $eduMapped = $this->mapEducations($educations);
        $eduCont = $this->extraEducations($educations);

        $childrenSplit = $this->splitSlots($children, self::CHILD_SLOTS);
        $eligSplit = $this->splitSlots($eligMapped, self::ELIG_SLOTS);
        $workSplit = $this->splitSlots($workMapped, self::WORK_SLOTS);
        $trainSplit = $this->splitSlots($trainMapped, self::TRAIN_SLOTS);
        $volSplit = $this->splitSlots($volMapped, self::VOL_SLOTS);
        $skillSplit = $this->splitSlots($this->normalizeOtherList($skills), self::OTHER_SLOTS);
        $recogSplit = $this->splitSlots($this->normalizeOtherList($recognitions), self::OTHER_SLOTS);
        $memberSplit = $this->splitSlots($this->normalizeOtherList($memberships), self::OTHER_SLOTS);
        $refSplit = $this->splitSlots($references->map(fn ($row) => [
            'name' => $this->na($row->name ?? null),
            'address' => $this->na($row->address ?? null),
            'contact' => $this->na($row->telephone_no ?? null),
        ])->all(), self::REF_SLOTS);

        $showC5 = $trainSplit['cont'] !== [];
        $showC6 = $workSplit['cont'] !== [];
        $showC7 = $childrenSplit['cont'] !== [];
        $showC8 = $eduCont !== [];
        $showC9 = $eligSplit['cont'] !== [];
        $showC10 = $volSplit['cont'] !== [];
        $showC11 = $skillSplit['cont'] !== [] || $recogSplit['cont'] !== [] || $memberSplit['cont'] !== [];

        return [
            'backRoute' => $backRoute,
            'usesV2' => $usesV2,
            'employee' => $employee,
            'agencyEmployeeNo' => $employee->emp_id,
            'surname' => $this->na($employee->lastname),
            'firstname' => $this->na($employee->firstname),
            'middlename' => $this->na($employee->middlename),
            'nameExtension' => $this->na($employee->extension ?: $employee->suffix),
            'birthdate' => $this->dmy($usesV2 ? $employee->personal?->birthdate : $employee->birthdate),
            'birthplace' => $this->na($usesV2 ? $employee->personal?->birthplace : $employee->birthplace),
            'sex' => $this->na($usesV2 ? $employee->personal?->sex : $employee->gender),
            'sexMale' => $this->isSex($usesV2 ? $employee->personal?->sex : $employee->gender, 'male'),
            'sexFemale' => $this->isSex($usesV2 ? $employee->personal?->sex : $employee->gender, 'female'),
            'civilStatus' => $this->na($usesV2 ? $employee->personal?->civil_status : $employee->civil_stat),
            'civilFlags' => $this->civilFlags($usesV2 ? $employee->personal?->civil_status : $employee->civil_stat),
            'height' => $this->na($usesV2 ? $employee->personal?->height : $employee->height),
            'weight' => $this->na($usesV2 ? $employee->personal?->weight : $employee->weight),
            'bloodType' => $this->na($usesV2 ? $employee->personal?->blood_type : $employee->blood_type),
            'gsis' => $this->na($usesV2 ? $employee->governmentIds?->gsis_no : $employee->gsis_no),
            'pagibig' => $this->na($usesV2 ? $employee->governmentIds?->pagibig_no : $employee->pagibig_no),
            'philhealth' => $this->na($usesV2 ? $employee->governmentIds?->phic_no : $employee->phic_no),
            'sss' => $this->na($usesV2 ? $employee->governmentIds?->sss_no : $employee->sss_no),
            'tin' => $this->na($usesV2 ? $employee->governmentIds?->tin_no : $employee->tin_no),
            'umid' => $this->na($usesV2 ? $employee->governmentIds?->issued_id_no : $employee->govid_no),
            'philsys' => 'N/A',
            'citizenship' => $this->na($usesV2 ? $employee->personal?->citizenship : $employee->citizenship_id),
            'govIdType' => $this->na($usesV2 ? $employee->governmentIds?->issued_id_type : $employee->gov_id),
            'govIdDatePlace' => $this->na($usesV2 ? $employee->governmentIds?->issued_id_date_place : $employee->govid_dateplace),
            'isFilipino' => $this->isFilipino($usesV2 ? $employee->personal?->citizenship : $employee->citizenship_id),
            'residential' => $this->splitAddress($usesV2 ? $employee->personal?->residential_address : null),
            'permanent' => $this->splitAddress($usesV2 ? $employee->personal?->permanent_address : null),
            'telephone' => $this->na($usesV2 ? $employee->contact?->telephone_no : $employee->tel_no),
            'mobile' => $this->na($usesV2 ? $employee->contact?->mobile_no : $employee->mobile_no),
            'email' => $this->na($usesV2 ? $employee->contact?->email : $employee->email),
            'departmentName' => $this->na(EmployeeDirectoryQuery::departmentName($employee)),
            'positionName' => $this->na(EmployeeDirectoryQuery::positionName($employee)),
            'spouse' => $this->personBlock($spouse),
            'father' => $this->personBlock($father),
            'mother' => $this->personBlock($mother),
            'children' => $childrenSplit['main'],
            'childrenCont' => $childrenSplit['cont'],
            'educations' => $eduMapped,
            'educationsCont' => $eduCont,
            'eligibilities' => $eligSplit['main'],
            'eligibilitiesCont' => $eligSplit['cont'],
            'workExperiences' => $workSplit['main'],
            'workExperiencesCont' => $workSplit['cont'],
            'trainings' => $trainSplit['main'],
            'trainingsCont' => $trainSplit['cont'],
            'voluntaryWorks' => $volSplit['main'],
            'voluntaryWorksCont' => $volSplit['cont'],
            'skills' => $skillSplit['main'],
            'skillsCont' => $skillSplit['cont'],
            'recognitions' => $recogSplit['main'],
            'recognitionsCont' => $recogSplit['cont'],
            'memberships' => $memberSplit['main'],
            'membershipsCont' => $memberSplit['cont'],
            'references' => $refSplit['main'],
            'showC5' => $showC5,
            'showC6' => $showC6,
            'showC7' => $showC7,
            'showC8' => $showC8,
            'showC9' => $showC9,
            'showC10' => $showC10,
            'showC11' => $showC11,
            'dateAccomplished' => now()->format('d/m/Y'),
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return array{main: list<mixed|null>, cont: list<mixed>}
     */
    private function splitSlots(array $items, int $slots): array
    {
        $items = array_values($items);

        return [
            'main' => array_pad(array_slice($items, 0, $slots), $slots, null),
            'cont' => array_slice($items, $slots),
        ];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function normalizeOtherList(array $items): array
    {
        if ($items === [] || ($items === ['N/A'])) {
            return [];
        }

        return array_values(array_filter($items, fn ($item) => $item !== null && $item !== ''));
    }

    /**
     * Extra education rows beyond the five fixed C1 levels → C8.
     *
     * @param  Collection<int, object>  $educations
     * @return list<array{level:string,school:string,course:string,from:string,to:string,units:string,year:string,honors:string}>
     */
    private function extraEducations(Collection $educations): array
    {
        $used = [];
        $levels = ['ELEMENTARY', 'SECONDARY', 'VOCATIONAL', 'COLLEGE', 'GRADUATE STUDIES'];

        foreach ($levels as $level) {
            $row = $educations->first(function ($item) use ($level) {
                return $this->matchesEducationLevel((string) ($item->education_level ?? ''), $level);
            });
            if ($row) {
                $used[] = spl_object_id($row);
            }
        }

        return $educations
            ->filter(fn ($row) => ! in_array(spl_object_id($row), $used, true))
            ->map(fn ($row) => [
                'level' => $this->educationLevelLabel($row->education_level ?? null),
                'school' => $this->na($row->school ?? null),
                'course' => $this->na($row->education_title ?? null),
                'from' => $this->yearOnly($row->start_date ?? null),
                'to' => $this->yearOnly($row->end_date ?? null),
                'units' => $this->na($row->units ?? null),
                'year' => $this->na($row->year_graduated ?? null),
                'honors' => $this->na($row->honors ?? null),
            ])
            ->values()
            ->all();
    }

    private function matchesEducationLevel(string $value, string $level): bool
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return false;
        }

        $legacyLevels = [
            '0' => 'ELEMENTARY',
            '1' => 'SECONDARY',
            '2' => 'VOCATIONAL',
            '3' => 'COLLEGE',
            '4' => 'GRADUATE STUDIES',
        ];

        if (isset($legacyLevels[$value])) {
            return $legacyLevels[$value] === $level;
        }

        return str_contains($value, $level) || str_contains($level, $value)
            || ($level === 'GRADUATE STUDIES' && (str_contains($value, 'MASTER') || str_contains($value, 'DOCTOR') || str_contains($value, 'GRADUATE')))
            || ($level === 'VOCATIONAL' && (str_contains($value, 'VOCATIONAL') || str_contains($value, 'TRADE')))
            || ($level === 'SECONDARY' && (str_contains($value, 'HIGH SCHOOL') || str_contains($value, 'SECONDARY') || str_contains($value, 'JUNIOR') || str_contains($value, 'SENIOR')))
            || ($level === 'ELEMENTARY' && (str_contains($value, 'ELEMENTARY') || str_contains($value, 'PRIMARY')))
            || ($level === 'COLLEGE' && (str_contains($value, 'COLLEGE') || str_contains($value, 'BACHELOR') || str_contains($value, 'TERTIARY')));
    }

    private function educationLevelLabel(mixed $value): string
    {
        $normalized = strtoupper(trim((string) ($value ?? '')));

        return [
            '0' => 'ELEMENTARY',
            '1' => 'SECONDARY',
            '2' => 'VOCATIONAL / TRADE COURSE',
            '3' => 'COLLEGE',
            '4' => 'GRADUATE STUDIES',
        ][$normalized] ?? $this->na($value);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $needles
     */
    private function firstByRelationship(Collection $rows, array $needles): ?object
    {
        $needles = array_map('strtoupper', $needles);

        return $rows->first(function ($row) use ($needles) {
            return in_array(strtoupper(trim((string) ($row->relationship ?? ''))), $needles, true);
        });
    }

    /**
     * @return array{surname:string,firstname:string,middlename:string,extension:string,occupation:string,employer:string,address:string,telephone:string}
     */
    private function personBlock(?object $row): array
    {
        if (! $row) {
            return [
                'surname' => 'N/A',
                'firstname' => 'N/A',
                'middlename' => 'N/A',
                'extension' => 'N/A',
                'occupation' => 'N/A',
                'employer' => 'N/A',
                'address' => 'N/A',
                'telephone' => 'N/A',
            ];
        }

        return [
            'surname' => $this->na($row->lastname ?? null),
            'firstname' => $this->na($row->firstname ?? null),
            'middlename' => $this->na($row->middlename ?? null),
            'extension' => $this->na($row->extension ?? null),
            'occupation' => $this->na($row->occupation ?? null),
            'employer' => $this->na($row->employer_name ?? null),
            'address' => $this->na($row->employer_address ?? null),
            'telephone' => $this->na($row->telephone_no ?? null),
        ];
    }

    private function fullName(object $row): string
    {
        $name = collect([$row->firstname ?? null, $row->middlename ?? null, $row->lastname ?? null, $row->extension ?? null])
            ->filter()
            ->implode(' ');

        return $this->na($name !== '' ? $name : null);
    }

    /**
     * @param  Collection<int, object>  $educations
     * @return list<array{level:string,school:string,course:string,from:string,to:string,units:string,year:string,honors:string}>
     */
    private function mapEducations(Collection $educations): array
    {
        $levels = ['ELEMENTARY', 'SECONDARY', 'VOCATIONAL', 'COLLEGE', 'GRADUATE STUDIES'];
        $mapped = [];

        foreach ($levels as $level) {
            $row = $educations->first(fn ($item) => $this->matchesEducationLevel((string) ($item->education_level ?? ''), $level));

            $mapped[] = [
                'level' => $level,
                'school' => $this->na($row?->school ?? null),
                'course' => $this->na($row?->education_title ?? null),
                'from' => $this->yearOnly($row?->start_date ?? null),
                'to' => $this->yearOnly($row?->end_date ?? null),
                'units' => $this->na($row?->units ?? null),
                'year' => $this->na($row?->year_graduated ?? null),
                'honors' => $this->na($row?->honors ?? null),
            ];
        }

        return $mapped;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $types
     * @return list<string>
     */
    private function otherByType(Collection $rows, array $types): array
    {
        $types = array_map('strtolower', $types);

        $matched = $rows->filter(function ($row) use ($types) {
            $type = strtolower(trim((string) (\App\Support\Hris\PdsFieldMaps::otherInfoTypeKey($row->type ?? '') ?: ($row->type ?? ''))));

            foreach ($types as $needle) {
                if ($type !== '' && ($type === $needle || str_contains($type, $needle))) {
                    return true;
                }
            }

            return false;
        })->map(fn ($row) => $this->na($row->title ?? null))->values()->all();

        return $matched !== [] ? $matched : ['N/A'];
    }

    private function gradeStep(mixed $grade, mixed $step): string
    {
        $grade = trim((string) ($grade ?? ''));
        $step = trim((string) ($step ?? ''));

        if ($grade === '' && $step === '') {
            return 'N/A';
        }

        if ($grade !== '' && $step !== '') {
            return sprintf('%s-%s', $grade, $step);
        }

        return $grade !== '' ? $grade : $step;
    }

    private function dmy(mixed $value): string
    {
        if ($value === null || $value === '' || $value === 'N/A') {
            return 'N/A';
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->format('d/m/Y');
            }

            return \Carbon\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    private function yearOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        try {
            if ($value instanceof CarbonInterface) {
                return $value->format('Y');
            }

            $string = trim((string) $value);
            if (preg_match('/^\d{4}$/', $string)) {
                return $string;
            }

            return \Carbon\Carbon::parse($string)->format('Y');
        } catch (\Throwable) {
            return 'N/A';
        }
    }

    private function na(mixed $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        $string = trim((string) $value);

        return $string === '' ? 'N/A' : $string;
    }

    private function isSex(mixed $value, string $expected): bool
    {
        $sex = strtolower(trim((string) $value));

        return match ($expected) {
            'male' => in_array($sex, ['male', 'm'], true),
            'female' => in_array($sex, ['female', 'f'], true),
            default => false,
        };
    }

    /**
     * @return array{single:bool,married:bool,widowed:bool,separated:bool,other:bool,otherText:string}
     */
    private function civilFlags(mixed $value): array
    {
        $status = strtolower(trim((string) $value));
        $known = ['single', 'married', 'widowed', 'widow', 'widower', 'separated'];

        return [
            'single' => str_contains($status, 'single'),
            'married' => str_contains($status, 'married'),
            'widowed' => str_contains($status, 'widow'),
            'separated' => str_contains($status, 'separated'),
            'other' => $status !== '' && $status !== 'n/a' && ! collect($known)->contains(fn ($k) => str_contains($status, $k)),
            'otherText' => ($status !== '' && $status !== 'n/a' && ! collect($known)->contains(fn ($k) => str_contains($status, $k)))
                ? (string) $value
                : '',
        ];
    }

    private function isFilipino(mixed $value): bool
    {
        $citizenship = strtolower(trim((string) $value));

        return $citizenship === '' || $citizenship === 'n/a' || str_contains($citizenship, 'filipino') || $citizenship === '1';
    }

    /**
     * @return array{house:string,street:string,subdivision:string,barangay:string,city:string,province:string,zip:string}
     */
    private function splitAddress(?string $address): array
    {
        $empty = [
            'house' => 'N/A',
            'street' => 'N/A',
            'subdivision' => 'N/A',
            'barangay' => 'N/A',
            'city' => 'N/A',
            'province' => 'N/A',
            'zip' => 'N/A',
        ];

        if ($address === null || trim($address) === '') {
            return $empty;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/,+/', $address) ?: [])));

        return [
            'house' => $parts[0] ?? 'N/A',
            'street' => $parts[1] ?? 'N/A',
            'subdivision' => $parts[2] ?? 'N/A',
            'barangay' => $parts[3] ?? 'N/A',
            'city' => $parts[4] ?? 'N/A',
            'province' => $parts[5] ?? ($parts[4] ?? 'N/A'),
            'zip' => $parts[6] ?? 'N/A',
        ];
    }
}
