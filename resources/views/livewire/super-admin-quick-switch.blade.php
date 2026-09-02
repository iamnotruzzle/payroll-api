<div
    x-data
    x-on:keydown.ctrl.shift.s.window.prevent="erpOverlay.open($wire, 'super-admin-switch', { password: '' })"
>
    <x-setup-form-modal name="super-admin-switch" title="Super-admin access" size="sm">
        <form wire:submit="elevate" class="space-y-4">
            <div>
                <p class="text-sm text-slate-700">Enter the protected switch password to unlock super-admin access for this session.</p>
                <p class="mt-1 text-xs text-slate-500">Elevated access remains active until you log out.</p>
            </div>

            <label class="block">
                <span class="text-xs font-semibold uppercase text-slate-500">Switch password</span>
                <input
                    wire:model="password"
                    type="password"
                    autocomplete="off"
                    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Enter protected password"
                >
                @error('password')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </label>

            <div class="flex justify-end gap-2 border-t pt-4">
                <button x-on:click="erpOverlay.close('super-admin-switch')" type="button" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="elevate" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                    <span wire:loading.remove wire:target="elevate">Unlock access</span>
                    <span wire:loading wire:target="elevate">Verifying...</span>
                </button>
            </div>
        </form>
    </x-setup-form-modal>
</div>
