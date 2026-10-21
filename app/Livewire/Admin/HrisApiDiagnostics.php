<?php

namespace App\Livewire\Admin;

use App\Services\Payroll\HrisApiDiagnosticsService;
use App\Models\Payroll\Canonical\Employee;
use App\Models\Payroll\Canonical\Department;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

class HrisApiDiagnostics extends Component
{
    public string $endpoint = 'timekeeping';

    public string $period = '';

    public string $from = '';

    public string $to = '';

    public array $employeeIds = [];

    public array $departmentIds = [];

    public string $divisionIds = '';

    public array $groups = [];

    public string $payrollType = '';

    public string $year = 'all';

    public bool $dateRange = false;

    public bool $activeOnly = false;

    public bool $includeInactive = false;

    #[Locked]
    public ?array $result = null;

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('super-admin') || auth()->user()?->hasRole('admin'), 403);
    }

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->period = now()->subMonthNoOverflow()->format('Y-m');
    }

    public function updatedEndpoint(): void
    {
        $this->result = null;
        $this->resetValidation();
    }

    public function runTest(HrisApiDiagnosticsService $service): void
    {
        $this->authorizeAdmin();
        $this->result = null;
        $this->resetValidation();
        $this->validate(['endpoint' => ['required', Rule::in(array_keys(HrisApiDiagnosticsService::FILTERS))]]);
        $filters = HrisApiDiagnosticsService::FILTERS[$this->endpoint];
        $rules = [];
        foreach (['divisionIds', 'payrollType'] as $field) {
            $rules[$field] = ['string', 'max:2000'];
        }
        $rules += [
            'employeeIds' => ['array', 'max:500'], 'employeeIds.*' => ['string', 'max:80'],
            'departmentIds' => ['array', 'max:500'], 'departmentIds.*' => ['integer', 'min:1'],
            'groups' => ['array', 'max:3'], 'groups.*' => [Rule::in(['OSEC', 'COS', 'COSSG'])],
        ];
        foreach (['divisionIds'] as $field) {
            $rules[$field][] = 'regex:/^\s*(?:[1-9]\d*\s*(?:,\s*[1-9]\d*\s*)*)?$/';
        }
        if ($this->endpoint === 'leaves' || ($this->endpoint === 'timekeeping' && $this->dateRange)) {
            $rules += ['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']];
        } elseif ($this->endpoint === 'timekeeping') {
            $rules['period'] = ['required', 'date_format:Y-m'];
        }
        if ($this->endpoint === 'salary-rates') {
            $rules['year'] = ['required', 'regex:/^(all|[12][0-9]{3})$/'];
        }
        $this->validate($rules);
        $parameters = [];
        foreach (['employeeIds' => 'emp_ids', 'departmentIds' => 'department_ids', 'divisionIds' => 'division_ids', 'groups' => 'groups', 'payrollType' => 'type'] as $property => $parameter) {
            $value = $this->{$property};
            if (! in_array($parameter, $filters, true) || (is_array($value) ? $value === [] : trim($value) === '')) {
                continue;
            }
            $values = array_values(array_unique(array_filter(array_map(fn ($item) => trim((string) $item), is_array($value) ? $value : explode(',', $value)), fn ($item) => $item !== '')));
            if ($parameter === 'type') {
                $parameters[$parameter] = trim($this->{$property});
            } elseif ($this->endpoint === 'basic-pay') {
                $parameters[$parameter] = in_array($parameter, ['department_ids', 'division_ids'], true)
                    ? array_map('intval', $values) : ($parameter === 'emp_ids' && $values === ['all'] ? 'all' : $values);
            } else {
                $parameters[$parameter] = implode(',', $values);
            }
        }
        if (in_array('active_only', $filters, true)) {
            $parameters['active_only'] = $this->activeOnly ? 'true' : 'false';
        }
        if ($this->endpoint === 'basic-pay') {
            $parameters['include_inactive'] = $this->includeInactive;
        }
        if ($this->endpoint === 'salary-rates') {
            $parameters['year'] = $this->year;
        }
        if ($this->endpoint === 'leaves' || ($this->endpoint === 'timekeeping' && $this->dateRange)) {
            $parameters += ['from' => $this->from, 'to' => $this->to];
        } elseif ($this->endpoint === 'timekeeping') {
            $parameters['period'] = $this->period;
        }
        if ($this->endpoint === 'basic-pay' && ! isset($parameters['emp_ids']) && empty($parameters['department_ids']) && empty($parameters['division_ids']) && empty($parameters['type'])) {
            $parameters['emp_ids'] = 'all';
        }
        $key = 'hris-api-diagnostics:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->addError('endpoint', 'Too many tests. Wait one minute and try again.');

            return;
        }
        RateLimiter::hit($key, 60);
        try {
            $this->result = $service->inspect($this->endpoint, $parameters);
        } catch (RuntimeException $e) {
            $this->addError('endpoint', $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.hris-api-diagnostics', ['resources' => collect(config('hris_api.resources'))
            ->only(array_keys(HrisApiDiagnosticsService::FILTERS))->filter(fn ($resource) => $resource['enabled'] ?? false),
            'employees' => Schema::connection('payroll')->hasTable('payroll_canonical_employees')
                ? Employee::query()->orderBy('lastname')->orderBy('firstname')->get(['emp_id', 'firstname', 'lastname']) : collect(),
            'departments' => Schema::connection('payroll')->hasTable('payroll_canonical_departments')
                ? Department::query()->orderBy('name')->get(['external_id', 'name']) : collect(),
            'groupOptions' => ['OSEC' => 'OSEC — Regular', 'COS' => 'COS — Contract of Service', 'COSSG' => 'COSSG'],
        ]);
    }
}
