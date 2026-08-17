<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">User Accounts</h2>
            <p class="text-sm text-slate-600">Manage logins and role assignments for HRIS accounts.</p>
        </div>
        @can('admin.users.manage')
            <button type="button" x-on:click="erpOverlay.open($wire, 'user-account', { editingId: null, empId: null, username: '', password: '', userLevel: null, pimsRole: null, selectedRoles: [], selectedPermissions: [] })" class="inline-flex items-center justify-center rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">
                Add Account
            </button>
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Search accounts</span>
                <input wire:model.lazy="search" type="search" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="Search employee, ID, or username">
            </label>
            <label class="flex items-center gap-2 text-xs font-semibold uppercase text-slate-500">
                Rows
                <select wire:model.live="perPage" class="rounded-md border border-slate-300 px-2 py-2 text-sm">
                    <option value="12">12</option>
                    <option value="24">24</option>
                    <option value="48">48</option>
                </select>
            </label>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Employee</th>
                    <th class="px-4 py-3 text-left">Username</th>
                    <th class="px-4 py-3 text-left">Roles</th>
                    <th class="px-4 py-3 text-left">Account Levels</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($accounts as $account)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-slate-800">{{ $account->employee?->full_name ?: 'Unlinked employee' }}</p>
                            <p class="text-xs text-slate-500">{{ $account->emp_id }} @if($account->employee?->department) &middot; {{ $account->employee->department->department_name ?? $account->employee->department->name ?? '' }} @endif</p>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-700">{{ $account->username }}</td>
                        <td class="px-4 py-3">
                            <div class="flex max-w-md flex-wrap gap-1.5">
                                @forelse ($account->roles as $role)
                                    <span class="rounded-full bg-[#f1f2ff] px-2 py-1 text-xs font-semibold text-[#696cff]">{{ $role->display_name ?: str($role->name)->headline() }}</span>
                                @empty
                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">No role</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            <span>Level {{ $account->user_level ?? '-' }}</span>
                            <span class="mx-1 text-slate-300">/</span>
                            <span>PIMS {{ $account->pims_role ?? '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('admin.users.manage')
                                <button type="button" x-on:click="erpOverlay.open($wire, 'user-account', { editingId: {{ $account->userid }}, empId: @js($account->emp_id), username: @js((string) $account->username), password: '', userLevel: @js($account->user_level), pimsRole: @js($account->pims_role), selectedRoles: @js($account->roles->pluck('name')->values()->all()), selectedPermissions: @js($account->permissions->pluck('name')->values()->all()) }, true)" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                                <button wire:click="resetPassword({{ $account->userid }})" wire:confirm="Reset password for {{ $account->username }}?" type="button" class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">Reset</button>
                                <button wire:click="deleteAccount({{ $account->userid }})" wire:confirm="Delete account {{ $account->username }}? Roles will be cleared." type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                            @else
                                <span class="text-xs text-slate-400">View only</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-slate-500">No user accounts found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div>
        {{ $accounts->links() }}
    </div>

    <x-setup-form-drawer name="user-account" title="Add Account" edit-title="Edit Account" size="wide">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(460px,0.9fr)]">
                <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h4 class="text-sm font-semibold text-slate-800">Employee Details</h4>
                    </div>
                    <div class="space-y-4 p-4">
                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-slate-500">Employee</span>
                            <select wire:model="empId" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                <option value="">Select employee</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->emp_id }}">{{ $employee->lastname }}, {{ $employee->firstname }} {{ $employee->middlename ? mb_substr($employee->middlename, 0, 1).'.' : '' }} &middot; {{ $employee->emp_id }}</option>
                                @endforeach
                            </select>
                            @error('empId') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500">User Level</span>
                                <input wire:model="userLevel" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500">PIMS Role</span>
                                <input wire:model="pimsRole" type="number" min="0" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                            </label>
                        </div>

                        <section class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <h5 class="text-sm font-semibold text-slate-800">Role Templates</h5>
                                <span class="text-xs text-slate-500">{{ count($selectedRoles) }} selected</span>
                            </div>
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ($roles as $role)
                                    <label class="flex items-start gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">
                                        <input wire:model="selectedRoles" value="{{ $role->name }}" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                                        <span>
                                            <span class="block font-semibold text-slate-800">{{ $role->display_name ?: str($role->name)->headline() }}</span>
                                            <span class="block text-xs text-slate-500">{{ $role->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedRoles.*') <span class="mt-2 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </section>
                    </div>
                </section>

                <div class="space-y-4">
                    <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-4 py-3">
                            <h4 class="text-sm font-semibold text-slate-800">Account Login</h4>
                        </div>
                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500">Username</span>
                                <input wire:model="username" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @error('username') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500" x-text="editing ? 'New Password' : 'Password'">Password</span>
                                <input wire:model="password" type="password" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" x-bind:placeholder="editing ? 'Leave blank to keep current' : ''">
                                @error('password') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    </section>

                    <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                            <h4 class="text-sm font-semibold text-slate-800">Module Access</h4>
                            <span class="text-xs text-slate-500">{{ count($selectedPermissions) }} direct grants</span>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach ($permissionGroups as $group => $permissions)
                                <div class="grid gap-3 px-4 py-3 md:grid-cols-[150px_minmax(0,1fr)]">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800">{{ $group }}</p>
                                        <p class="text-xs text-slate-500">{{ count($permissions) }} actions</p>
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($permissions as $permission => $label)
                                            <label class="flex items-start gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                                <input wire:model="selectedPermissions" value="{{ $permission }}" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-slate-300">
                                                <span>
                                                    <span class="block font-semibold text-slate-800">{{ $label }}</span>
                                                    <span class="block text-xs text-slate-500">{{ $permission }}</span>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('selectedPermissions.*') <span class="block px-4 pb-3 text-xs text-red-600">{{ $message }}</span> @enderror
                    </section>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <button x-on:click="erpOverlay.close('user-account')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save Account</button>
            </div>
        </form>
    </x-setup-form-drawer>
</div>
