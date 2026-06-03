<?php

namespace App\Livewire\Admin;

use Database\Seeders\RBACSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissions extends Component
{
    public bool $drawerOpen = false;

    public bool $permissionModalOpen = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $displayName = '';

    public string $description = '';

    public bool $isActive = true;

    public array $selectedPermissions = [];

    public string $permissionName = '';

    public function render()
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->where('guard_name', 'web')
            ->orderBy('display_name')
            ->orderBy('name')
            ->get();

        $seededGroups = RBACSeeder::groupedPermissions();
        $seededNames = collect($seededGroups)->flatMap(fn (array $permissions) => array_keys($permissions));
        $customPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $seededNames)
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => Str::headline(str_replace('.', ' ', $name))])
            ->all();

        if ($customPermissions !== []) {
            $seededGroups['Custom'] = $customPermissions;
        }

        return view('livewire.admin.roles-permissions', [
            'roles' => $roles,
            'permissionGroups' => $seededGroups,
        ]);
    }

    public function createRole(): void
    {
        abort_unless(auth()->user()?->can('admin.roles.manage'), 403);

        $this->resetRoleForm();
        $this->drawerOpen = true;
    }

    public function editRole(int $id): void
    {
        abort_unless(auth()->user()?->can('admin.roles.manage'), 403);

        $role = Role::with('permissions')->findOrFail($id);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->displayName = (string) ($role->display_name ?: Str::headline($role->name));
        $this->description = (string) $role->description;
        $this->isActive = (bool) $role->is_active;
        $this->selectedPermissions = $role->permissions->pluck('name')->values()->all();
        $this->drawerOpen = true;
        $this->resetValidation();
    }

    public function saveRole(): void
    {
        abort_unless(auth()->user()?->can('admin.roles.manage'), 403);

        $this->name = Str::slug($this->name);

        $data = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($this->editingId),
            ],
            'displayName' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'isActive' => ['boolean'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $data['name'],
                'display_name' => $data['displayName'],
                'description' => $data['description'],
                'is_active' => $data['isActive'],
                'guard_name' => 'web',
            ],
        );

        $role->syncPermissions($data['selectedPermissions']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->drawerOpen = false;
        $this->resetRoleForm();

        session()->flash('status', 'Role saved.');
    }

    public function openPermissionModal(): void
    {
        abort_unless(auth()->user()?->can('admin.roles.manage'), 403);

        $this->permissionName = '';
        $this->permissionModalOpen = true;
        $this->resetValidation('permissionName');
    }

    public function savePermission(): void
    {
        abort_unless(auth()->user()?->can('admin.roles.manage'), 403);

        $this->permissionName = Str::lower(trim($this->permissionName));

        $data = $this->validate([
            'permissionName' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ]);

        Permission::findOrCreate($data['permissionName'], 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->permissionModalOpen = false;
        session()->flash('status', 'Permission added.');
    }

    public function closeDrawer(): void
    {
        $this->drawerOpen = false;
        $this->resetRoleForm();
    }

    public function closePermissionModal(): void
    {
        $this->permissionModalOpen = false;
        $this->permissionName = '';
        $this->resetValidation('permissionName');
    }

    private function resetRoleForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'displayName',
            'description',
            'isActive',
            'selectedPermissions',
        ]);
        $this->isActive = true;
        $this->resetValidation();
    }
}
