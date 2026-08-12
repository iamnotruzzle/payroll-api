<?php

namespace App\Livewire\Employees;

use App\Models\Hris\UserAccount;
use App\Models\Role;
use App\Services\Hris\EmployeeProfileWriteService;
use Livewire\Component;

class EmployeeAccountPanel extends Component
{
    public string $empId;

    /** @var list<string> */
    public array $selectedRoles = [];

    public function mount(string $empId): void
    {
        abort_unless(
            auth()->user()?->can('admin.users.view') || auth()->user()?->can('admin.users.manage'),
            403
        );
        $this->empId = $empId;
        $this->hydrateRoles();
    }

    public function provisionAccount(EmployeeProfileWriteService $writer): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        if (UserAccount::query()->where('emp_id', $this->empId)->exists()) {
            session()->flash('status', 'An account is already linked to this employee.');

            return;
        }

        $temporary = $writer->provisionDefaultAccount(
            $this->empId,
            auth()->user()?->emp_id
        );

        $this->hydrateRoles();
        session()->flash('status', "Account created. Username: {$this->empId}. Temporary password: {$temporary}");
    }

    public function resetPassword(): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $account = UserAccount::query()->where('emp_id', $this->empId)->firstOrFail();
        $temporary = 'ChangeMe'.random_int(1000, 9999).'!';
        $account->password = $temporary;
        $account->login_attempt = 0;
        $account->save();

        session()->flash('status', "Password reset for {$account->username}. Temporary password: {$temporary}");
    }

    public function saveRoles(): void
    {
        abort_unless(auth()->user()?->can('admin.users.manage'), 403);

        $data = $this->validate([
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:hris.roles,name'],
        ]);

        $account = UserAccount::query()->where('emp_id', $this->empId)->firstOrFail();
        $account->syncRoles($data['selectedRoles']);
        $this->hydrateRoles();

        session()->flash('status', 'Roles updated.');
    }

    public function render()
    {
        $account = UserAccount::query()
            ->where('emp_id', $this->empId)
            ->first();

        return view('livewire.employees.employee-account-panel', [
            'account' => $account,
            'roles' => $account ? $account->getRoleNames() : collect(),
            'allRoles' => Role::query()->where('guard_name', 'web')->orderBy('name')->get(),
            'canManage' => (bool) auth()->user()?->can('admin.users.manage'),
        ]);
    }

    private function hydrateRoles(): void
    {
        $account = UserAccount::query()->where('emp_id', $this->empId)->first();
        $this->selectedRoles = $account
            ? $account->getRoleNames()->values()->all()
            : [];
    }
}
