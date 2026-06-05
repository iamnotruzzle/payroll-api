<div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">Roles and Permissions</h2>
            <p class="text-sm text-slate-600">Manage access profiles and the permissions attached to each role.</p>
        </div>
        @can('admin.roles.manage')
            <div class="flex flex-wrap gap-2">
                <button wire:click="openPermissionModal" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Add Permission</button>
                <button wire:click="createRole" type="button" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Add Role</button>
            </div>
        @endcan
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($roles as $role)
            <article class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-slate-800">{{ $role->display_name ?: str($role->name)->headline() }}</h3>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">{{ $role->name }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $role->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $role->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p class="mt-3 line-clamp-2 min-h-[2.5rem] text-sm text-slate-600">{{ $role->description ?: 'No description.' }}</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase text-slate-500">Permissions</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">{{ $role->permissions_count }}</p>
                    </div>
                    <div class="rounded-md bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase text-slate-500">Users</p>
                        <p class="mt-1 text-lg font-semibold text-slate-800">{{ $role->users_count }}</p>
                    </div>
                </div>
                <div class="mt-4 text-right">
                    @can('admin.roles.manage')
                        <button wire:click="editRole({{ $role->id }})" type="button" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50">Edit</button>
                    @else
                        <span class="text-xs text-slate-400">View only</span>
                    @endcan
                </div>
            </article>
        @empty
            <div class="rounded-md border border-slate-200 bg-white px-4 py-8 text-center text-sm text-slate-500 shadow-sm md:col-span-2 xl:col-span-3">
                No roles found.
            </div>
        @endforelse
    </section>

    <section class="rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-800">Permission Catalog</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($permissionGroups as $group => $permissions)
                <details class="group" @if($loop->first) open @endif>
                    <summary class="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        <span>{{ $group }}</span>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ count($permissions) }}</span>
                    </summary>
                    <div class="grid gap-2 px-4 pb-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($permissions as $permission => $label)
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $permission }}</p>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </section>

    @if ($drawerOpen)
        <div class="fixed inset-0 z-50 overflow-hidden" role="dialog" aria-modal="true">
            <button wire:click="closeDrawer" type="button" class="fixed inset-0 h-full w-full bg-slate-950/30" aria-label="Close role drawer"></button>
            <aside class="absolute inset-y-0 right-0 flex h-dvh max-h-dvh w-full max-w-2xl flex-col overflow-hidden bg-white shadow-xl">
                <div class="shrink-0 flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 class="text-base font-semibold">{{ $editingId ? 'Edit Role' : 'Add Role' }}</h3>
                        <p class="text-xs text-slate-500">Use the accordions below to assign permissions.</p>
                    </div>
                    <button wire:click="closeDrawer" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50">Close</button>
                </div>

                <form wire:submit="saveRole" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500">Role Key</span>
                                <input wire:model="name" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="payroll-processor">
                                @error('name') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                            <label>
                                <span class="text-xs font-semibold uppercase text-slate-500">Display Name</span>
                                <input wire:model="displayName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                                @error('displayName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-xs font-semibold uppercase text-slate-500">Description</span>
                            <textarea wire:model="description" rows="3" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"></textarea>
                            @error('description') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                        </label>

                        <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                            <input wire:model="isActive" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                            <span>Active</span>
                        </label>

                        <section class="rounded-md border border-slate-200">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                                <h4 class="text-sm font-semibold text-slate-800">Permissions</h4>
                                <span class="text-xs text-slate-500">{{ count($selectedPermissions) }} selected</span>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @foreach ($permissionGroups as $group => $permissions)
                                    <details @if($loop->first) open @endif>
                                        <summary class="flex items-center justify-between gap-3 px-4 py-3 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                            <span>{{ $group }}</span>
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ count($permissions) }}</span>
                                        </summary>
                                        <div class="grid gap-2 px-4 pb-4 sm:grid-cols-2">
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
                                    </details>
                                @endforeach
                            </div>
                            @error('selectedPermissions.*') <span class="block px-4 pb-3 text-xs text-red-600">{{ $message }}</span> @enderror
                        </section>
                    </div>

                    <div class="shrink-0 flex items-center justify-end gap-2 border-t border-slate-200 px-5 py-4">
                        <button wire:click="closeDrawer" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save Role</button>
                    </div>
                </form>
            </aside>
        </div>
    @endif

    @if ($permissionModalOpen)
        <div class="fixed inset-0 z-50 grid place-items-center overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <button wire:click="closePermissionModal" type="button" class="fixed inset-0 h-full w-full bg-slate-950/30" aria-label="Close permission modal"></button>
            <form wire:submit="savePermission" class="relative flex max-h-[calc(100dvh-3rem)] w-full max-w-md flex-col overflow-hidden rounded-md bg-white shadow-xl">
                <div class="shrink-0 border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold">Add Permission</h3>
                </div>
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-5 py-4">
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-slate-500">Permission Key</span>
                        <input wire:model="permissionName" type="text" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="module.action">
                        @error('permissionName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                </div>
                <div class="shrink-0 flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
                    <button wire:click="closePermissionModal" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-semibold text-white hover:bg-[#5f61e6]">Save Permission</button>
                </div>
            </form>
        </div>
    @endif
</div>
