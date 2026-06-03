<?php

namespace App\Livewire\Admin;

use Database\Seeders\RBACSeeder;
use App\Models\Hris\Employee;
use App\Models\Hris\UserAccount;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserAccounts extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 12;

    public bool $drawerOpen = false;

    public ?int $editingId = null;

    public ?string $empId = null;

    public string $username = '';

    public string $password = '';

    public ?int $userLevel = null;

    public ?int $pimsRole = null;

    public array $selectedRoles = [];

    public array $selectedPermissions = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $accounts = UserAccount::query()
            ->with(['employee.department', 'roles'])
            ->when($this->search !== '', function ($query) {
                $search = trim($this->search);
                $tokens = preg_split('/\s+/', $search) ?: [];

                $query->where(function ($query) use ($search, $tokens) {
                    $query->where('emp_id', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($employeeQuery) use ($tokens) {
                            foreach ($tokens as $token) {
                                $employeeQuery->where(function ($nameQuery) use ($token) {
                                    $nameQuery->where('firstname', 'like', "%{$token}%")
                                        ->orWhere('lastname', 'like', "%{$token}%")
                                        ->orWhere('middlename', 'like', "%{$token}%");
                                });
                            }
                        });
                });
            })
            ->orderBy('username')
            ->paginate($this->perPage);

        return view('livewire.admin.user-accounts', [
            'accounts' => $accounts,
            'roles' => Role::query()->where('guard_name', 'web')->orderBy('display_name')->orderBy('name')->get(),
            'permissionGroups' => RBACSeeder::groupedPermissions(),
            'employees' => Employee::query()
                ->select(['emp_id', 'firstname', 'middlename', 'lastname', 'extension', 'department_id', 'is_active'])
                ->with('department')
                ->where('is_active', 'Y')
                ->whereDoesntHave('userAccount', function ($query) {
                    if ($this->editingId) {
                        $query->where('userid', '!=', $this->editingId);
                    }
                })
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->limit(75)
                ->get(),
        ]);
    }

    public function create(): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $this->resetForm();
        $this->drawerOpen = true;
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $account = UserAccount::with(['roles', 'permissions'])->findOrFail($id);

        $this->editingId = $account->userid;
        $this->empId = $account->emp_id;
        $this->username = (string) $account->username;
        $this->password = '';
        $this->userLevel = $account->user_level;
        $this->pimsRole = $account->pims_role;
        $this->selectedRoles = $account->roles->pluck('name')->values()->all();
        $this->selectedPermissions = $account->permissions->pluck('name')->values()->all();
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $data = $this->validate([
            'empId' => [
                'required',
                'string',
                'exists:mysql.tbl_employee,emp_id',
                Rule::unique('tbl_useraccount', 'emp_id')->ignore($this->editingId, 'userid'),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tbl_useraccount', 'username')->ignore($this->editingId, 'userid'),
            ],
            'password' => [$this->editingId ? 'nullable' : 'required', 'string', 'min:8'],
            'userLevel' => ['nullable', 'integer', 'min:0'],
            'pimsRole' => ['nullable', 'integer', 'min:0'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        $payload = [
            'emp_id' => $data['empId'],
            'username' => $data['username'],
            'user_level' => $data['userLevel'],
            'pims_role' => $data['pimsRole'],
        ];

        if ($data['password'] !== '') {
            $payload['password'] = $data['password'];
        }

        $account = UserAccount::query()->updateOrCreate(
            ['userid' => $this->editingId],
            $payload,
        );

        $account->syncRoles($data['selectedRoles']);
        $account->syncPermissions($data['selectedPermissions']);

        $this->drawerOpen = false;
        $this->resetForm();

        session()->flash('status', 'User account saved.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'empId',
            'username',
            'password',
            'userLevel',
            'pimsRole',
            'selectedRoles',
            'selectedPermissions',
        ]);
        $this->resetValidation();
    }
}
