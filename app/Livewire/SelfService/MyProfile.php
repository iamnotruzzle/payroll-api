<?php

namespace App\Livewire\SelfService;

use App\Services\Hris\EmployeePdsSectionService;
use App\Services\Hris\EmployeeProfileWriteService;
use App\Support\Hris\EmployeeDirectoryQuery;
use App\Support\Hris\PdsFieldMaps;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class MyProfile extends Component
{
    public string $empId = '';

    public bool $editing = false;

    public bool $mustUpdateProfile = false;

    public string $firstname = '';

    public string $middlename = '';

    public string $lastname = '';

    public string $extension = '';

    public string $prefix = '';

    public string $suffix = '';

    public string $email = '';

    public string $mobile_no = '';

    public string $telephone_no = '';

    public string $birthdate = '';

    public string $birthplace = '';

    public string $sex = '';

    public string $civil_status = '';

    public string $blood_type = '';

    public string $citizenship = '';

    public string $religion = '';

    public string $tin_no = '';

    public string $gsis_no = '';

    public string $pagibig_no = '';

    public string $phic_no = '';

    public string $sss_no = '';

    #[Url(as: 'section', except: 'dependents')]
    public string $section = 'dependents';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('self-service.profile') || auth()->user()?->can('self-service.access'), 403);

        $this->empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);

        $this->mustUpdateProfile = (int) (auth()->user()?->login_attempt ?? 1) === 0;
        $this->normalizeSection();

        if ($this->mustUpdateProfile) {
            $this->startEditing();
        }
    }

    public function startEditing(): void
    {
        $employee = EmployeeDirectoryQuery::findForProfile($this->empId);
        abort_unless($employee, 404);

        $this->firstname = (string) $employee->firstname;
        $this->middlename = (string) ($employee->middlename ?? '');
        $this->lastname = (string) $employee->lastname;
        $this->extension = (string) ($employee->extension ?? '');
        $this->prefix = (string) ($employee->prefix ?? '');
        $this->suffix = (string) ($employee->suffix ?? '');
        $this->email = (string) ($employee->email ?? '');
        $this->mobile_no = (string) ($employee->mobile_no ?? '');
        $this->telephone_no = (string) ($employee->tel_no ?? '');
        $this->birthdate = optional($employee->birthdate)->format('Y-m-d') ?? '';
        $this->birthplace = (string) ($employee->birthplace ?? '');
        $this->sex = (string) ($employee->gender ?? '');
        $this->civil_status = (string) ($employee->civil_stat ?? '');
        $this->blood_type = (string) ($employee->blood_type ?? '');
        $this->citizenship = (string) ($employee->citizenship_id ?? '');
        $this->religion = (string) ($employee->religion_id ?? '');
        $this->tin_no = (string) ($employee->tin_no ?? '');
        $this->gsis_no = (string) ($employee->gsis_no ?? '');
        $this->pagibig_no = (string) ($employee->pagibig_no ?? '');
        $this->phic_no = (string) ($employee->phic_no ?? '');
        $this->sss_no = (string) ($employee->sss_no ?? '');
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        if ($this->mustUpdateProfile) {
            return;
        }

        $this->editing = false;
        $this->resetValidation();
    }

    public function save(EmployeeProfileWriteService $writer): void
    {
        $data = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:32'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:64'],
            'telephone_no' => ['nullable', 'string', 'max:64'],
            'birthdate' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:16'],
            'civil_status' => ['nullable', 'string', 'max:32'],
            'blood_type' => ['nullable', 'string', 'max:8'],
            'citizenship' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'tin_no' => ['nullable', 'string', 'max:64'],
            'gsis_no' => ['nullable', 'string', 'max:64'],
            'pagibig_no' => ['nullable', 'string', 'max:64'],
            'phic_no' => ['nullable', 'string', 'max:64'],
            'sss_no' => ['nullable', 'string', 'max:64'],
        ]);

        $employee = EmployeeDirectoryQuery::findForProfile($this->empId);
        abort_unless($employee, 404);

        $writer->updateCoreProfile($this->empId, $data + [
            'date_hired' => optional($employee->date_hired)->format('Y-m-d') ?? '',
            'height' => $employee->height !== null ? (string) $employee->height : '',
            'weight' => $employee->weight !== null ? (string) $employee->weight : '',
            'issued_id_type' => (string) ($employee->gov_id ?? ''),
            'issued_id_no' => (string) ($employee->govid_no ?? ''),
            'issued_id_date_place' => (string) ($employee->govid_dateplace ?? ''),
            'is_related_third_degree' => (string) ($employee->is_degree3 ?? ''),
            'is_related_fourth_degree' => (string) ($employee->is_degree4 ?? ''),
            'is_admin_offense' => (string) ($employee->is_adminoffense ?? ''),
            'is_criminally_charged' => (string) ($employee->is_criminallycharged ?? ''),
            'is_convicted' => (string) ($employee->is_convictedtocourt ?? ''),
            'is_separated_service' => (string) ($employee->is_separated ?? ''),
            'is_election_candidate' => (string) ($employee->is_candidate ?? ''),
            'is_resigned_for_campaign' => (string) ($employee->is_campaign ?? ''),
            'is_immigrant' => (string) ($employee->is_immigrant ?? ''),
            'is_indigenous' => (string) ($employee->is_indigenous ?? ''),
            'is_pwd' => (string) ($employee->is_pwd ?? ''),
            'is_solo_parent' => (string) ($employee->is_soloparent ?? ''),
        ]);

        $writer->clearLoginAttempt($this->empId);
        auth()->user()?->refresh();

        $this->mustUpdateProfile = false;
        $this->editing = false;
        session()->flash('status', 'Profile updated. You can continue using the app.');
    }

    public function setSection(string $section): void
    {
        abort_unless(in_array($section, EmployeePdsSectionService::SECTIONS, true), 404);
        $this->section = $section;
    }

    private function normalizeSection(): void
    {
        if (! in_array($this->section, EmployeePdsSectionService::SECTIONS, true)) {
            $this->section = 'dependents';
        }
    }

    public function render(EmployeePdsSectionService $sections)
    {
        $employee = EmployeeDirectoryQuery::findForProfile($this->empId);
        abort_unless($employee, 404);

        $rows = $sections->list($this->empId, $this->section);

        return view('livewire.self-service.my-profile', [
            'employee' => $employee,
            'departmentName' => EmployeeDirectoryQuery::departmentName($employee),
            'positionName' => EmployeeDirectoryQuery::positionName($employee),
            'isActive' => EmployeeDirectoryQuery::isActive($employee),
            'sexLabel' => PdsFieldMaps::sexLabel($employee->gender),
            'civilStatusLabel' => PdsFieldMaps::civilStatusLabel($employee->civil_stat),
            'rows' => $rows,
            'otherInfoGroups' => $this->section === 'other_infos' ? $this->groupOtherInfos($rows) : [],
            'sectionLabels' => [
                'dependents' => 'Family / dependents',
                'educations' => 'Education',
                'eligibilities' => 'Eligibility',
                'work_experiences' => 'Work experience',
                'trainings' => 'Learning & development',
                'voluntary_works' => 'Voluntary work',
                'other_infos' => 'Other info',
                'references' => 'References',
            ],
        ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, array{label:string, items:Collection<int, object>}>
     */
    private function groupOtherInfos(Collection $rows): array
    {
        $groups = [
            'skill' => ['label' => 'Special Skills and Hobbies', 'items' => collect()],
            'recognition' => ['label' => 'Non-Academic Distinctions / Recognition', 'items' => collect()],
            'membership' => ['label' => 'Membership in Association / Organization', 'items' => collect()],
        ];

        foreach ($rows as $row) {
            $key = PdsFieldMaps::otherInfoTypeKey($row->type ?? null) ?: 'skill';
            if (! isset($groups[$key])) {
                $key = 'skill';
            }
            $groups[$key]['items']->push($row);
        }

        return $groups;
    }

    public static function displayDate(mixed $value, string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        try {
            return Carbon::parse((string) $value)->format('M j, Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    public static function displayDateRange(mixed $start, mixed $end, string $empty = '—'): string
    {
        $from = self::displayDate($start, '');
        $to = $end === null || $end === '' ? 'To present' : self::displayDate($end, '');

        if ($from === '' && ($end === null || $end === '')) {
            return $empty;
        }

        if ($from === '') {
            return $to;
        }

        return $from.' – '.$to;
    }

    public static function displayValue(mixed $value, string $empty = '—'): string
    {
        if ($value === null) {
            return $empty;
        }

        $string = trim((string) $value);

        return $string === '' ? $empty : $string;
    }
}
