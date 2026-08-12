<?php

namespace App\Livewire\Employees;

use App\Models\Hris\Department;
use App\Models\Hris\EmploymentStatus;
use App\Models\Hris\Position;
use App\Services\Hris\EmployeeProfileWriteService;
use Livewire\Component;

class EmployeeCreate extends Component
{
    public string $emp_id = '';

    public string $firstname = '';

    public string $middlename = '';

    public string $lastname = '';

    public string $extension = '';

    public string $prefix = '';

    public string $suffix = '';

    public string $date_hired = '';

    public string $birthdate = '';

    public string $sex = '';

    public string $email = '';

    public string $mobile_no = '';

    public string $telephone_no = '';

    public ?int $department_id = null;

    public ?int $position_id = null;

    public ?int $empstat_id = null;

    public bool $provision_account = true;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);
        $this->date_hired = now()->toDateString();
    }

    public function save(EmployeeProfileWriteService $writer)
    {
        abort_unless(auth()->user()?->can('employees.manage'), 403);

        $data = $this->validate([
            'emp_id' => ['required', 'string', 'max:32'],
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:32'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'date_hired' => ['nullable', 'date'],
            'birthdate' => ['required', 'date'],
            'sex' => ['nullable', 'string', 'max:16'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_no' => ['nullable', 'string', 'max:64'],
            'telephone_no' => ['nullable', 'string', 'max:64'],
            'department_id' => ['required', 'integer', 'exists:hris.tbl_department,department_id'],
            'position_id' => ['required', 'integer', 'exists:hris.tbl_position,position_id'],
            'empstat_id' => ['required', 'integer', 'exists:hris.tbl_employmentstat,empstat_id'],
            'provision_account' => ['boolean'],
        ]);

        $result = $writer->createEmployee([
            'emp_id' => $data['emp_id'],
            'firstname' => $data['firstname'],
            'middlename' => $data['middlename'] ?? '',
            'lastname' => $data['lastname'],
            'extension' => $data['extension'] ?? '',
            'prefix' => $data['prefix'] ?? '',
            'suffix' => $data['suffix'] ?? '',
            'date_hired' => $data['date_hired'] ?? '',
            'birthdate' => $data['birthdate'],
            'sex' => $data['sex'] ?? '',
            'email' => $data['email'] ?? '',
            'mobile_no' => $data['mobile_no'] ?? '',
            'telephone_no' => $data['telephone_no'] ?? '',
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'empstat_id' => $data['empstat_id'],
            'citizenship' => '',
            'religion' => '',
            'civil_status' => '',
            'blood_type' => '',
            'birthplace' => '',
            'height' => '',
            'weight' => '',
            'tin_no' => '',
            'gsis_no' => '',
            'pagibig_no' => '',
            'phic_no' => '',
            'sss_no' => '',
            'issued_id_type' => '',
            'issued_id_no' => '',
            'issued_id_date_place' => '',
        ], (bool) $data['provision_account']);

        $message = 'Employee '.$result['employee']->emp_id.' created.';
        if ($result['temporary_password']) {
            $message .= ' Temporary password: '.$result['temporary_password'];
        }

        session()->flash('status', $message);

        return $this->redirect(route('employees.show', $result['employee']->emp_id), navigate: true);
    }

    public function render()
    {
        return view('livewire.employees.employee-create', [
            'departments' => Department::query()->orderBy('department')->get(),
            'positions' => Position::query()->orderBy('position_title')->limit(500)->get(),
            'employmentStatuses' => EmploymentStatus::query()->orderBy('empstat_id')->get(),
        ]);
    }
}
