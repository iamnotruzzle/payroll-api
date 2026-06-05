@php
    $permissionLabels = collect($permissionGroups)->flatMap(fn (array $permissions) => $permissions);
    $pagePermissions = collect($permissionPages);
    $roles = collect($roleDefinitions)->map(function (array $definition, string $name) use ($permissionLabels, $pagePermissions) {
        $pages = collect($definition['permissions'])
            ->flatMap(fn (string $permission) => $pagePermissions->get($permission, []))
            ->unique(fn (array $page) => $page['route'] ?? $page['href'] ?? $page['label'])
            ->values();

        return [
            'name' => $name,
            'display_name' => $definition['display_name'],
            'description' => $definition['description'],
            'permissions' => collect($definition['permissions'])
                ->map(fn (string $permission) => [
                    'name' => $permission,
                    'label' => $permissionLabels->get($permission, $permission),
                ])
                ->values(),
            'pages' => $pages,
        ];
    });

    $pageUrl = fn (array $page) => isset($page['route']) ? route($page['route']) : $page['href'];
@endphp

<x-layouts.app title="Roles and Permissions Manual">
    <section class="mx-auto max-w-7xl space-y-6">
        <div class="border-b border-slate-200 pb-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Reference Manual</p>
            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Roles and Permissions</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Use this guide to understand which RBAC roles are available, what each permission means,
                and which application pages become accessible when a permission is assigned.
            </p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="xl:sticky xl:top-6 xl:self-start">
                <nav class="rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Manual Navigation</p>
                    <div class="mt-3 space-y-1">
                        <a href="#roles" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">1. Role Access</a>
                        <a href="#permissions" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">2. Permission Access</a>
                        <a href="#legacy" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">3. Legacy Backfill</a>
                        <a href="#notes" class="block rounded-md px-3 py-2 text-slate-700 hover:bg-slate-100">4. Admin Notes</a>
                    </div>
                </nav>
            </aside>

            <div class="space-y-6">
                <section id="roles" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">1. Role Access</p>
                            <h3 class="mt-1 text-lg font-semibold">Accessible Pages by Role</h3>
                        </div>
                        <span class="rounded-md bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">{{ $roles->count() }} roles</span>
                    </div>

                    <div class="mt-5 space-y-4">
                        @foreach ($roles as $role)
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-base font-semibold text-slate-950">{{ $role['display_name'] }}</h4>
                                        <p class="mt-1 text-xs font-medium text-slate-500">{{ $role['name'] }}</p>
                                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $role['description'] }}</p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">{{ $role['permissions']->count() }} permissions</span>
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Permissions</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($role['permissions'] as $permission)
                                                <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700" title="{{ $permission['label'] }}">
                                                    {{ $permission['name'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Accessible Pages</p>
                                        @if ($role['pages']->isEmpty())
                                            <p class="mt-2 text-sm text-slate-500">No page-level access is mapped to this role.</p>
                                        @else
                                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                @foreach ($role['pages'] as $page)
                                                    <a href="{{ $pageUrl($page) }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700">
                                                        {{ $page['label'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="permissions" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">2. Permission Access</p>
                    <h3 class="mt-1 text-lg font-semibold">Accessible Pages by Permission</h3>

                    <div class="mt-4 space-y-5">
                        @foreach ($permissionGroups as $group => $permissions)
                            <div>
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-2">
                                    <h4 class="text-sm font-semibold text-slate-950">{{ $group }}</h4>
                                    <span class="text-xs font-medium text-slate-500">{{ count($permissions) }} permissions</span>
                                </div>
                                <div class="mt-3 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <tr>
                                                <th class="px-3 py-3">Permission</th>
                                                <th class="px-3 py-3">Purpose</th>
                                                <th class="px-3 py-3">Pages Opened</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach ($permissions as $permission => $label)
                                                <tr>
                                                    <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-slate-700">{{ $permission }}</td>
                                                    <td class="px-3 py-3 text-slate-600">{{ $label }}</td>
                                                    <td class="px-3 py-3">
                                                        @php($pages = collect($permissionPages[$permission] ?? []))
                                                        @if ($pages->isEmpty())
                                                            <span class="text-sm text-slate-400">No direct page gate; used for actions inside pages.</span>
                                                        @else
                                                            <div class="flex flex-wrap gap-2">
                                                                @foreach ($pages as $page)
                                                                    <a href="{{ $pageUrl($page) }}" class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100">
                                                                        {{ $page['label'] }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section id="legacy" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">3. Legacy Backfill</p>
                    <h3 class="mt-1 text-lg font-semibold">Default HRIS Account Mapping</h3>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">user_level mapping</h4>
                            <dl class="mt-3 space-y-2 text-sm">
                                @foreach ([1 => 'super-admin', 2 => 'admin', 3 => 'scheduler + schedule-approver', 4 => 'scheduler', 5 => 'employee'] as $legacy => $mapped)
                                    <div class="flex justify-between gap-4">
                                        <dt class="font-mono text-xs text-slate-500">{{ $legacy }}</dt>
                                        <dd class="text-right font-medium text-slate-700">{{ $mapped }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">pims_role mapping</h4>
                            <dl class="mt-3 space-y-2 text-sm">
                                @foreach ([1 => 'super-admin', 2 => 'admin', 3 => 'payroll-approver', 4 => 'payroll-processor + timekeeper', 5 => 'timekeeper'] as $legacy => $mapped)
                                    <div class="flex justify-between gap-4">
                                        <dt class="font-mono text-xs text-slate-500">{{ $legacy }}</dt>
                                        <dd class="text-right font-medium text-slate-700">{{ $mapped }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                </section>

                <section id="notes" class="scroll-mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">4. Admin Notes</p>
                    <h3 class="mt-1 text-lg font-semibold">How to Read the Matrix</h3>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <h4 class="text-sm font-semibold text-emerald-900">Page gates</h4>
                            <p class="mt-2 text-sm leading-6 text-emerald-800">The visible pages above come from route-level permission middleware.</p>
                        </div>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <h4 class="text-sm font-semibold text-blue-900">Action gates</h4>
                            <p class="mt-2 text-sm leading-6 text-blue-800">Manage, approve, configure, and generate permissions may control buttons or workflows inside an already visible page.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <h4 class="text-sm font-semibold text-slate-950">Manual updates</h4>
                            <p class="mt-2 text-sm leading-6 text-slate-600">When roles or route gates change, update the RBAC seeder and this page mapping together.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </section>
</x-layouts.app>
