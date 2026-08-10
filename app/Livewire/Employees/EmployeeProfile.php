<?php

namespace App\Livewire\Employees;

use App\Services\Hris\EmployeeProfileWriteService;
use App\Support\Hris\EmployeeDirectoryQuery;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

class EmployeeProfile extends Component
{
    public string $empId;

    public bool $editing = false;

    public bool $showDeactivateModal = false;

    public string $firstname = '';

    public string $middlename = '';

    public string $lastname = '';

    public string $extension = '';

    public string $prefix = '';

    public string $suffix = '';

    public string $date_hired = '';

    public string $email = '';

    public string $mobile_no = '';

    public string $telephone_no = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_no = '';

    public string $birthdate = '';

    public string $birthplace = '';

    public string $sex = '';

    public string $civil_status = '';

    public string $blood_type = '';

    public string $citizenship = '';

    public string $religion = '';

    public string $height = '';

    public string $weight = '';

    public string $residential_address = '';

    public string $permanent_address = '';

    public string $tin_no = '';

    public string $gsis_no = '';

    public string $pagibig_no = '';

    public string $phic_no = '';

    public string $sss_no = '';

    public string $issued_id_type = '';

    public string $issued_id_no = '';

    public string $issued_id_date_place = '';

    public string $is_related_third_degree = '';

    public string $is_related_fourth_degree = '';

    public string $is_admin_offense = '';

    public string $is_criminally_charged = '';

    public string $is_convicted = '';

    public string $is_separated_service = '';

    public string $is_election_candidate = '';

    public string $is_resigned_for_campaign = '';

    public string $is_immigrant = '';

    public string $is_indigenous = '';

    public string $is_pwd = '';

    public string $is_solo_parent = '';

    public string $date_separated = '';

    public string $separation_reason = '';

    public function mount(string $empId): void
    {
        abort_unless($this->canView(), 403);
        $this->empId = $empId;
    }

    public function startEditing(): void
    {
        abort_unless($this->canManage(), 403);

        $employee = EmployeeDirectoryQuery::findForProfile($this->empId);
        abort_unless($employee, 404);

        $usesV2 = EmployeeDirectoryQuery::usesV2();

        $this->firstname = (string) $employee->firstname;
        $this->middlename = (string) ($employee->middlename ?? '');
        $this->lastname = (string) $employee->lastname;
        $this->extension = (string) ($employee->extension ?? '');
        $this->prefix = (string) ($employee->prefix ?? '');
        $this->suffix = (string) ($employee->suffix ?? '');
        $this->date_hired = optional($employee->date_hired)->format('Y-m-d') ?? '';

        if ($usesV2) {
            $this->email = (string) ($employee->contact?->email ?? '');
            $this->mobile_no = (string) ($employee->contact?->mobile_no ?? '');
            $this->telephone_no = (string) ($employee->contact?->telephone_no ?? '');
            $this->emergency_contact_name = (string) ($employee->contact?->emergency_contact_name ?? '');
            $this->emergency_contact_no = (string) ($employee->contact?->emergency_contact_no ?? '');
            $this->birthdate = optional($employee->personal?->birthdate)->format('Y-m-d') ?? '';
            $this->birthplace = (string) ($employee->personal?->birthplace ?? '');
            $this->sex = (string) ($employee->personal?->sex ?? '');
            $this->civil_status = (string) ($employee->personal?->civil_status ?? '');
            $this->blood_type = (string) ($employee->personal?->blood_type ?? '');
            $this->citizenship = (string) ($employee->personal?->citizenship ?? '');
            $this->religion = (string) ($employee->personal?->religion ?? '');
            $this->height = $employee->personal?->height !== null ? (string) $employee->personal->height : '';
            $this->weight = $employee->personal?->weight !== null ? (string) $employee->personal->weight : '';
            $this->residential_address = (string) ($employee->personal?->residential_address ?? '');
            $this->permanent_address = (string) ($employee->personal?->permanent_address ?? '');
            $this->tin_no = (string) ($employee->governmentIds?->tin_no ?? '');
            $this->gsis_no = (string) ($employee->governmentIds?->gsis_no ?? '');
            $this->pagibig_no = (string) ($employee->governmentIds?->pagibig_no ?? '');
            $this->phic_no = (string) ($employee->governmentIds?->phic_no ?? '');
            $this->sss_no = (string) ($employee->governmentIds?->sss_no ?? '');
            $this->issued_id_type = (string) ($employee->governmentIds?->issued_id_type ?? '');
            $this->issued_id_no = (string) ($employee->governmentIds?->issued_id_no ?? '');
            $this->issued_id_date_place = (string) ($employee->governmentIds?->issued_id_date_place ?? '');
            $this->is_related_third_degree = $this->boolToYn($employee->personal?->is_related_third_degree);
            $this->is_related_fourth_degree = $this->boolToYn($employee->personal?->is_related_fourth_degree);
            $this->is_admin_offense = $this->boolToYn($employee->personal?->is_admin_offense);
            $this->is_criminally_charged = $this->boolToYn($employee->personal?->is_criminally_charged);
            $this->is_convicted = $this->boolToYn($employee->personal?->is_convicted);
            $this->is_separated_service = $this->boolToYn($employee->personal?->is_separated_service);
            $this->is_election_candidate = $this->boolToYn($employee->personal?->is_election_candidate);
            $this->is_resigned_for_campaign = $this->boolToYn($employee->personal?->is_resigned_for_campaign);
            $this->is_immigrant = $this->boolToYn($employee->personal?->is_immigrant);
            $this->is_indigenous = $this->boolToYn($employee->personal?->is_indigenous);
            $this->is_pwd = $this->boolToYn($employee->personal?->is_pwd);
            $this->is_solo_parent = $this->boolToYn($employee->personal?->is_solo_parent);
        } else {
            $this->email = (string) ($employee->email ?? '');
            $this->mobile_no = (string) ($employee->mobile_no ?? '');
            $this->telephone_no = (string) ($employee->tel_no ?? '');
            $this->emergency_contact_name = '';
            $this->emergency_contact_no = '';
            $this->birthdate = optional($employee->birthdate)->format('Y-m-d') ?? '';
            $this->birthplace = (string) ($employee->birthplace ?? '');
            $this->sex = (string) ($employee->gender ?? '');
            $this->civil_status = (string) ($employee->civil_stat ?? '');
            $this->blood_type = (string) ($employee->blood_type ?? '');
            $this->citizenship = (string) ($employee->citizenship_id ?? '');
            $this->religion = (string) ($employee->religion_id ?? '');
            $this->height = $employee->height !== null ? (string) $employee->height : '';
            $this->weight = $employee->weight !== null ? (string) $employee->weight : '';
            $this->residential_address = '';
            $this->permanent_address = '';
            $this->tin_no = (string) ($employee->tin_no ?? '');
            $this->gsis_no = (string) ($employee->gsis_no ?? '');
            $this->pagibig_no = (string) ($employee->pagibig_no ?? '');
            $this->phic_no = (string) ($employee->phic_no ?? '');
            $this->sss_no = (string) ($employee->sss_no ?? '');
            $this->issued_id_type = (string) ($employee->gov_id ?? '');
            $this->issued_id_no = (string) ($employee->govid_no ?? '');
            $this->issued_id_date_place = (string) ($employee->govid_dateplace ?? '');
            $this->is_related_third_degree = $this->legacyYn($employee->is_degree3 ?? null);
            $this->is_related_fourth_degree = $this->legacyYn($employee->is_degree4 ?? null);
            $this->is_admin_offense = $this->legacyYn($employee->is_adminoffense ?? null);
            $this->is_criminally_charged = $this->legacyYn($employee->is_criminallycharged ?? null);
            $this->is_convicted = $this->legacyYn($employee->is_convictedtocourt ?? null);
            $this->is_separated_service = $this->legacyYn($employee->is_separated ?? null);
            $this->is_election_candidate = $this->legacyYn($employee->is_candidate ?? null);
            $this->is_resigned_for_campaign = $this->legacyYn($employee->is_campaign ?? null);
            $this->is_immigrant = $this->legacyYn($employee->is_immigrant ?? null);
            $this->is_indigenous = $this->legacyYn($employee->is_indigenous ?? null);
            $this->is_pwd = $this->legacyYn($employee->is_pwd ?? null);
            $this->is_solo_parent = $this->legacyYn($employee->is_soloparent ?? null);
        }

        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function save(EmployeeProfileWriteService $writer): void
    {
        abort_unless($this->canManage(), 403);

        $data = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:32'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'date_hired' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:64'],
            'telephone_no' => ['nullable', 'string', 'max:64'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_no' => ['nullable', 'string', 'max:64'],
            'birthdate' => ['nullable', 'date'],
            'birthplace' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:16'],
            'civil_status' => ['nullable', 'string', 'max:32'],
            'blood_type' => ['nullable', 'string', 'max:8'],
            'citizenship' => ['nullable', 'string', 'max:255'],
            'religion' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'residential_address' => ['nullable', 'string', 'max:2000'],
            'permanent_address' => ['nullable', 'string', 'max:2000'],
            'tin_no' => ['nullable', 'string', 'max:64'],
            'gsis_no' => ['nullable', 'string', 'max:64'],
            'pagibig_no' => ['nullable', 'string', 'max:64'],
            'phic_no' => ['nullable', 'string', 'max:64'],
            'sss_no' => ['nullable', 'string', 'max:64'],
            'issued_id_type' => ['nullable', 'string', 'max:128'],
            'issued_id_no' => ['nullable', 'string', 'max:128'],
            'issued_id_date_place' => ['nullable', 'string', 'max:255'],
            'is_related_third_degree' => ['nullable', 'string', 'max:1'],
            'is_related_fourth_degree' => ['nullable', 'string', 'max:1'],
            'is_admin_offense' => ['nullable', 'string', 'max:1'],
            'is_criminally_charged' => ['nullable', 'string', 'max:1'],
            'is_convicted' => ['nullable', 'string', 'max:1'],
            'is_separated_service' => ['nullable', 'string', 'max:1'],
            'is_election_candidate' => ['nullable', 'string', 'max:1'],
            'is_resigned_for_campaign' => ['nullable', 'string', 'max:1'],
            'is_immigrant' => ['nullable', 'string', 'max:1'],
            'is_indigenous' => ['nullable', 'string', 'max:1'],
            'is_pwd' => ['nullable', 'string', 'max:1'],
            'is_solo_parent' => ['nullable', 'string', 'max:1'],
        ]);

        $writer->updateCoreProfile($this->empId, $data);
        $this->editing = false;
        session()->flash('status', 'Employee profile updated.');
    }

    public function openDeactivateModal(): void
    {
        abort_unless($this->canManage(), 403);
        $this->date_separated = now()->toDateString();
        $this->separation_reason = '';
        $this->showDeactivateModal = true;
    }

    public function closeDeactivateModal(): void
    {
        $this->showDeactivateModal = false;
        $this->resetValidation();
    }

    public function deactivate(EmployeeProfileWriteService $writer): void
    {
        abort_unless($this->canManage(), 403);

        $meta = $this->validate([
            'date_separated' => ['nullable', 'date'],
            'separation_reason' => [EmployeeDirectoryQuery::usesV2() ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        try {
            $writer->setActive($this->empId, false, $meta);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['separation_reason' => $e->getMessage()]);
        }

        $this->showDeactivateModal = false;
        session()->flash('status', 'Employee deactivated.');
    }

    public function activate(EmployeeProfileWriteService $writer): void
    {
        abort_unless($this->canManage(), 403);
        $writer->setActive($this->empId, true);
        session()->flash('status', 'Employee reactivated.');
    }

    public function render()
    {
        abort_unless($this->canView(), 403);

        $employee = EmployeeDirectoryQuery::findForProfile($this->empId);
        abort_unless($employee, 404);

        return view('livewire.employees.employee-profile', [
            'employee' => $employee,
            'usesV2' => EmployeeDirectoryQuery::usesV2(),
            'departmentName' => EmployeeDirectoryQuery::departmentName($employee),
            'positionName' => EmployeeDirectoryQuery::positionName($employee),
            'isActive' => EmployeeDirectoryQuery::isActive($employee),
            'canManage' => $this->canManage(),
        ]);
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

    private function boolToYn(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return $value ? 'Y' : 'N';
    }

    private function legacyYn(mixed $value): string
    {
        $raw = strtoupper(trim((string) ($value ?? '')));
        if ($raw === '') {
            return '';
        }

        return in_array($raw, ['Y', '1', 'TRUE', 'YES'], true) ? 'Y' : 'N';
    }
}
