<?php

namespace App\Livewire\Employees;

use App\Services\Hris\EmployeePdsSectionService;
use Livewire\Attributes\Url;
use Livewire\Component;

class EmployeePdsSections extends Component
{
    public string $empId;

    #[Url(as: 'section', except: 'dependents')]
    public string $section = 'dependents';

    public bool $editing = false;

    public ?int $editingId = null;

    public string $firstname = '';

    public string $middlename = '';

    public string $lastname = '';

    public string $extension = '';

    public string $relationship = '';

    public string $birthdate = '';

    public string $sex = '';

    public string $occupation = '';

    public string $employer_name = '';

    public string $employer_address = '';

    public string $telephone_no = '';

    public string $education_level = '';

    public string $education_title = '';

    public string $school = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $units = '';

    public string $year_graduated = '';

    public string $honors = '';

    public string $url = '';

    public string $eligibility_lookup_id = '';

    public string $title = '';

    public string $confer_date = '';

    public string $confer_place = '';

    public string $rating = '';

    public string $license_no = '';

    public string $exp_date = '';

    public string $work_position = '';

    public string $work_status_id = '';

    public string $company_name = '';

    public string $company_address = '';

    public string $salary = '';

    public string $salary_grade = '';

    public string $step_inc = '';

    public bool $is_government = false;

    public string $training_name = '';

    public string $training_venue = '';

    public string $sponsor = '';

    public string $hours = '';

    public string $type_id = '';

    public string $type_name = '';

    public string $organization_name = '';

    public string $position = '';

    public string $type = '';

    public string $name = '';

    public string $address = '';

    public function mount(string $empId): void
    {
        abort_unless($this->canView(), 403);
        $this->empId = $empId;
        $this->normalizeSection();
    }

    public function setSection(string $section): void
    {
        abort_unless(in_array($section, EmployeePdsSectionService::SECTIONS, true), 404);
        $this->section = $section;
        $this->resetForm();
    }

    private function normalizeSection(): void
    {
        if (! in_array($this->section, EmployeePdsSectionService::SECTIONS, true)) {
            $this->section = 'dependents';
        }
    }

    public function startCreate(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetForm();
        $this->editing = true;
        $this->editingId = null;
    }

    public function startEdit(int $recordId, EmployeePdsSectionService $sections): void
    {
        abort_unless($this->canManage(), 403);

        $row = $sections->list($this->empId, $this->section)->firstWhere('id', $recordId);
        abort_unless($row, 404);

        $this->resetForm();
        $this->editing = true;
        $this->editingId = $recordId;

        foreach ((array) $row as $key => $value) {
            if ($key === 'id' || $key === 'label' || $key === 'sex_label' || $key === 'education_level_label' || $key === 'type_label' || $key === 'work_status' || ! property_exists($this, $key)) {
                continue;
            }

            if (is_bool($this->{$key})) {
                $this->{$key} = (bool) $value;
            } else {
                $this->{$key} = $value === null ? '' : (string) $value;
            }
        }

        if (property_exists($this, 'work_status_id') && ($this->work_status_id === '' || $this->work_status_id === null) && isset($row->work_status_id)) {
            $this->work_status_id = $row->work_status_id === null ? '' : (string) $row->work_status_id;
        }
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function updatedEligibilityLookupId(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $label = app(EmployeePdsSectionService::class)
            ->eligibilityOptions()
            ->firstWhere('id', (int) $value)
            ?->label;

        if ($label) {
            $this->title = (string) $label;
        }
    }

    public function updatedTypeId(mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $label = app(EmployeePdsSectionService::class)
            ->trainingTypeOptions()
            ->firstWhere('id', (int) $value)
            ?->label;

        if ($label) {
            $this->type_name = (string) $label;
        }
    }

    public function save(EmployeePdsSectionService $sections): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate($this->rulesForSection());
        $sections->save($this->empId, $this->section, $data, $this->editingId);
        $this->resetForm();
        session()->flash('pds_status', 'PDS section saved.');
    }

    public function deleteRecord(int $recordId, EmployeePdsSectionService $sections): void
    {
        abort_unless($this->canManage(), 403);
        $sections->delete($this->empId, $this->section, $recordId);
        if ($this->editingId === $recordId) {
            $this->resetForm();
        }
        session()->flash('pds_status', 'Record deleted.');
    }

    public function render(EmployeePdsSectionService $sections)
    {
        abort_unless($this->canView(), 403);

        return view('livewire.employees.employee-pds-sections', [
            'rows' => $sections->list($this->empId, $this->section),
            'eligibilityOptions' => $sections->eligibilityOptions(),
            'trainingTypeOptions' => $sections->trainingTypeOptions(),
            'employmentStatusOptions' => $sections->employmentStatusOptions(),
            'educationLevelOptions' => $sections->educationLevelOptions(),
            'otherInfoTypeOptions' => $sections->otherInfoTypeOptions(),
            'canManage' => $this->canManage(),
            'sectionLabels' => [
                'dependents' => 'Family / dependents',
                'educations' => 'Education',
                'eligibilities' => 'Civil service eligibility',
                'work_experiences' => 'Work experience',
                'trainings' => 'Learning & development',
                'voluntary_works' => 'Voluntary work',
                'other_infos' => 'Other info',
                'references' => 'Character references',
            ],
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rulesForSection(): array
    {
        return match ($this->section) {
            'dependents' => [
                'firstname' => ['required', 'string', 'max:255'],
                'middlename' => ['nullable', 'string', 'max:255'],
                'lastname' => ['required', 'string', 'max:255'],
                'extension' => ['nullable', 'string', 'max:32'],
                'relationship' => ['nullable', 'string', 'max:64'],
                'birthdate' => ['nullable', 'date'],
                'sex' => ['nullable', 'string', 'max:16'],
                'occupation' => ['nullable', 'string', 'max:255'],
                'employer_name' => ['nullable', 'string', 'max:255'],
                'employer_address' => ['nullable', 'string', 'max:1000'],
                'telephone_no' => ['nullable', 'string', 'max:64'],
            ],
            'educations' => [
                'education_level' => ['nullable', 'string', 'max:64'],
                'education_title' => ['nullable', 'string', 'max:255'],
                'school' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'units' => ['nullable', 'numeric'],
                'year_graduated' => ['nullable', 'string', 'max:16'],
                'honors' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'string', 'max:255'],
            ],
            'eligibilities' => [
                'eligibility_lookup_id' => ['nullable'],
                'title' => ['nullable', 'string', 'max:255'],
                'confer_date' => ['nullable', 'date'],
                'confer_place' => ['nullable', 'string', 'max:255'],
                'rating' => ['nullable', 'numeric'],
                'license_no' => ['nullable', 'string', 'max:100'],
                'exp_date' => ['nullable', 'date'],
            ],
            'work_experiences' => [
                'work_position' => ['nullable', 'string', 'max:255'],
                'work_status_id' => ['nullable'],
                'company_name' => ['nullable', 'string', 'max:255'],
                'company_address' => ['nullable', 'string', 'max:1000'],
                'salary' => ['nullable', 'numeric'],
                'salary_grade' => ['nullable', 'string', 'max:32'],
                'step_inc' => ['nullable', 'integer', 'min:0', 'max:32767'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'is_government' => ['boolean'],
            ],
            'trainings' => [
                'training_name' => ['nullable', 'string', 'max:5000'],
                'training_venue' => ['nullable', 'string', 'max:2000'],
                'sponsor' => ['nullable', 'string', 'max:2000'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'hours' => ['nullable', 'numeric'],
                'type_id' => ['nullable'],
                'type_name' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'string', 'max:255'],
            ],
            'voluntary_works' => [
                'organization_name' => ['nullable', 'string', 'max:255'],
                'start_date' => ['nullable', 'date'],
                'end_date' => ['nullable', 'date'],
                'hours' => ['nullable', 'numeric'],
                'position' => ['nullable', 'string', 'max:255'],
            ],
            'other_infos' => [
                'title' => ['nullable', 'string', 'max:255'],
                'type' => ['nullable', 'string', 'max:64'],
            ],
            'references' => [
                'name' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'telephone_no' => ['nullable', 'string', 'max:100'],
            ],
            default => [],
        };
    }

    private function resetForm(): void
    {
        $this->editing = false;
        $this->editingId = null;
        $this->resetValidation();

        foreach ([
            'firstname', 'middlename', 'lastname', 'extension', 'relationship', 'birthdate', 'sex',
            'occupation', 'employer_name', 'employer_address', 'telephone_no', 'education_level',
            'education_title', 'school', 'start_date', 'end_date', 'units', 'year_graduated', 'honors',
            'url', 'eligibility_lookup_id', 'title', 'confer_date', 'confer_place', 'rating',
            'license_no', 'exp_date', 'work_position', 'work_status_id', 'company_name', 'company_address',
            'salary', 'salary_grade', 'step_inc', 'training_name', 'training_venue', 'sponsor',
            'hours', 'type_id', 'type_name', 'organization_name', 'position', 'type', 'name', 'address',
        ] as $field) {
            $this->{$field} = '';
        }

        $this->is_government = false;
    }

    private function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->can('employees.view') || $user?->can('employees.manage'));
    }

    private function canManage(): bool
    {
        return (bool) auth()->user()?->can('employees.manage');
    }
}
