<?php

namespace App\Livewire\SelfService;

use App\Services\Hris\EmployeePdsSectionService;
use App\Support\Hris\EmployeeDirectoryQuery;
use App\Support\Hris\PdsFieldMaps;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class MyProfile extends Component
{
    public string $empId = '';

    #[Url(as: 'section', except: 'dependents')]
    public string $section = 'dependents';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('self-service.profile') || auth()->user()?->can('self-service.access'), 403);

        $this->empId = (string) (auth()->user()?->emp_id ?? '');
        abort_unless($this->empId !== '', 404);

        $this->normalizeSection();
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

        $usesV2 = EmployeeDirectoryQuery::usesV2();
        $rows = $sections->list($this->empId, $this->section);

        return view('livewire.self-service.my-profile', [
            'employee' => $employee,
            'usesV2' => $usesV2,
            'departmentName' => EmployeeDirectoryQuery::departmentName($employee),
            'positionName' => EmployeeDirectoryQuery::positionName($employee),
            'isActive' => EmployeeDirectoryQuery::isActive($employee),
            'sexLabel' => PdsFieldMaps::sexLabel($usesV2 ? $employee->personal?->sex : $employee->gender),
            'civilStatusLabel' => PdsFieldMaps::civilStatusLabel($usesV2 ? $employee->personal?->civil_status : $employee->civil_stat),
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
