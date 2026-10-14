@if (request()->attributes->get('role_preview_allowed'))
    @php
        $previewRole = request()->attributes->get('role_preview');
        $previewRoles = \App\Models\Role::query()->where('guard_name', 'web')->where('is_active', true)->where('name', '!=', 'super-admin')->orderBy('display_name')->get();
        $previewLabel = $previewRole?->display_name ?: $previewRole?->name;
    @endphp
    <div x-data class="flex min-w-0 items-center">
        <button type="button" x-on:click="erpOverlay.open(null, 'role-preview', {}, false)"
            class="erp-user-trigger flex min-w-0 items-center gap-2 rounded-xl px-2 py-1.5 text-sm font-semibold"
            aria-haspopup="dialog" aria-label="{{ $previewRole ? 'Role preview: '.$previewLabel : 'Open role preview' }}"
            title="{{ $previewRole ? 'Previewing: '.$previewLabel : 'Role preview' }}">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            <span class="hidden max-w-40 truncate sm:block">{{ $previewRole ? 'Preview: '.$previewLabel : 'Role preview' }}</span>
            @if ($previewRole)
                <span class="erp-status-dot shrink-0" aria-hidden="true"></span>
            @endif
        </button>
        <x-setup-form-modal name="role-preview" title="Role preview" size="sm">
            <div class="space-y-4 text-left">
                @if ($previewRole)
                    <p class="text-sm font-semibold">Currently previewing {{ $previewLabel }}</p>
                @endif
                <p class="erp-subtle text-sm">See the apps, menus, and access available to another role. Employee records remain your own, and actions affect real data.</p>
                <form method="POST" action="{{ route('admin.role-preview.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="role-preview-id" class="block text-sm font-semibold">Role to preview</label>
                        <select id="role-preview-id" name="role_id" required class="erp-user-menu mt-2 w-full min-w-0 rounded-md border px-3 py-2 text-sm">
                            <option value="">Choose a role</option>
                            @foreach ($previewRoles as $role)
                                <option value="{{ $role->id }}" @selected($previewRole?->id === $role->id)>{{ $role->display_name ?: $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 border-t pt-4">
                        <button type="button" x-on:click="erpOverlay.close('role-preview')" class="rounded-md border px-4 py-2 text-sm font-medium">Cancel</button>
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $previewRole ? 'Switch role' : 'Preview role' }}</button>
                    </div>
                </form>
                @if ($previewRole)
                    <form method="POST" action="{{ route('admin.role-preview.destroy') }}" class="border-t pt-4">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-md border px-4 py-2 text-sm font-semibold">Exit preview and restore my access</button>
                    </form>
                @endif
            </div>
        </x-setup-form-modal>
    </div>
@endif
