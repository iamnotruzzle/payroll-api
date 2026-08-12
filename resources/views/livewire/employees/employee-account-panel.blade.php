<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">User account</h3>
            <p class="text-sm text-slate-600">Login account and Spatie roles linked to this employee.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @canany(['admin.users.view', 'admin.users.manage'])
                <a href="{{ route('admin.user-accounts', ['search' => $empId]) }}"
                   class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    User Accounts
                </a>
            @endcanany
        </div>
    </div>

    @if ($account)
        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm space-y-4">
            <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-slate-500">Username</dt>
                    <dd class="font-medium text-slate-800">{{ $account->username }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">User ID</dt>
                    <dd class="font-medium text-slate-800">{{ $account->userid }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Login attempt</dt>
                    <dd class="font-medium text-slate-800">{{ $account->login_attempt }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500">Current roles</dt>
                    <dd class="mt-1 flex flex-wrap gap-1">
                        @forelse ($roles as $role)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $role }}</span>
                        @empty
                            <span class="text-slate-500">No roles assigned</span>
                        @endforelse
                    </dd>
                </div>
            </dl>

            @if ($canManage)
                <div class="border-t border-slate-100 pt-4">
                    <p class="text-sm font-medium text-slate-800">Assign roles</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($allRoles as $role)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}" class="rounded border-slate-300">
                                {{ $role->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('selectedRoles') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('selectedRoles.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="saveRoles"
                                class="rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">
                            Save roles
                        </button>
                        <button type="button" wire:click="resetPassword" wire:confirm="Reset password and force profile gate?"
                                class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-100">
                            Reset password
                        </button>
                    </div>
                </div>
            @endif
        </section>
    @else
        <div class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
            <p>No account linked.</p>
            @if ($canManage)
                <button type="button" wire:click="provisionAccount"
                        class="mt-3 rounded-md bg-[#696cff] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#5f61e6]">
                    Create default account
                </button>
            @endif
        </div>
    @endif
</div>
