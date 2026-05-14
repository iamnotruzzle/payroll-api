<section class="space-y-4">
    <div>
        <h2 class="text-xl font-semibold">Schedule Print Settings</h2>
        <p class="text-sm text-slate-600">Manage printed schedule branding and assignatories for {{ $department?->department ?? 'your department' }}.</p>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-[420px_minmax(0,1fr)]">
        <div class="space-y-4">
            <form wire:submit="saveSettings" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <h3 class="font-semibold">Branding and Layout</h3>
                    <p class="mt-1 text-sm text-slate-600">These values appear on printed and exported schedule pages.</p>
                </div>

                <div>
                    <label class="text-sm font-medium">Organization Name</label>
                    <input wire:model="organization_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-sm font-medium">Schedule Heading</label>
                    <input wire:model="schedule_heading" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="text-sm font-medium">Area Label</label>
                    <input wire:model="area_label" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>

                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save Text Settings</button>
            </form>

            <form wire:submit="uploadLogos" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div>
                    <h3 class="font-semibold">Upload Logos</h3>
                    <p class="mt-1 text-sm text-slate-600">Upload one or more logos, then drag them in the preview to set print placement.</p>
                </div>

                <div>
                    <label class="text-sm font-medium">Logo Files</label>
                    <input wire:model="logos" type="file" multiple accept="image/*" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('logos') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    @error('logos.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">
                    <span wire:loading.remove wire:target="uploadLogos">Upload Logos</span>
                    <span wire:loading wire:target="uploadLogos">Uploading...</span>
                </button>
            </form>

            <form wire:submit="saveSignatory" class="space-y-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h3 class="font-semibold">{{ $editingSignatoryId ? 'Edit Assignatory' : 'New Assignatory' }}</h3>

                <div>
                    <label class="text-sm font-medium">Purpose</label>
                    <input wire:model="purpose" placeholder="Prepared By, Recommended By, Approved By" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('purpose') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Name</label>
                    <input wire:model="person_name" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    @error('person_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-sm font-medium">Designation</label>
                    <input wire:model="designation" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium">Display Order</label>
                        <input wire:model="display_order" type="number" min="1" max="99" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-sm">
                        <input wire:model="is_active" type="checkbox">
                        Active
                    </label>
                </div>

                <div class="flex gap-2">
                    <button class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-600">Save Assignatory</button>
                    <button wire:click="resetSignatoryForm" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Clear</button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
        <div
            class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
            x-data="{
                activeId: null,
                startX: 0,
                startY: 0,
                startLogoX: 0,
                startLogoY: 0,
                logos: @js($printLogos->map(fn ($logo) => [
                    'id' => $logo->id,
                    'label' => $logo->label,
                    'url' => asset('storage/'.$logo->path),
                    'x' => (float) $logo->x_position,
                    'y' => (float) $logo->y_position,
                    'width' => $logo->width,
                    'active' => $logo->is_active,
                ])->values()),
                startDrag(event, id) {
                    const logo = this.logos.find((item) => item.id === id);

                    if (! logo) return;

                    this.activeId = id;
                    this.startX = event.clientX;
                    this.startY = event.clientY;
                    this.startLogoX = logo.x;
                    this.startLogoY = logo.y;
                },
                drag(event) {
                    if (! this.activeId) return;

                    const preview = this.$refs.preview;
                    const logo = this.logos.find((item) => item.id === this.activeId);

                    if (! preview || ! logo) return;

                    const rect = preview.getBoundingClientRect();
                    const nextX = this.startLogoX + ((event.clientX - this.startX) / rect.width * 100);
                    const nextY = this.startLogoY + ((event.clientY - this.startY) / rect.height * 100);

                    logo.x = Math.max(0, Math.min(95, nextX));
                    logo.y = Math.max(0, Math.min(95, nextY));
                },
                stopDrag() {
                    if (! this.activeId) return;

                    const logo = this.logos.find((item) => item.id === this.activeId);
                    this.activeId = null;

                    if (logo) {
                        this.$wire.updateLogoPlacement(logo.id, logo.x, logo.y);
                    }
                },
            }"
            x-on:mousemove.window="drag($event)"
            x-on:mouseup.window="stopDrag()"
            x-on:print-logos-updated.window="logos = $event.detail.logos"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold">Logo Placement Preview</h3>
                    <p class="mt-1 text-sm text-slate-600">Drag logos to position them on the printed page. Placement is saved when you release the mouse.</p>
                </div>
                <span class="rounded-md bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700">Folio landscape preview</span>
            </div>

            <div
                x-ref="preview"
                class="relative mt-4 aspect-[13/8.5] overflow-hidden rounded-md border border-slate-300 bg-white shadow-inner"
            >
                <div class="absolute inset-x-0 top-[5%] text-center">
                    <div class="text-xs font-bold tracking-wide">{{ $organization_name }}</div>
                    <div class="mt-1 text-xs font-bold">{{ strtoupper($department?->department ?? 'DEPARTMENT') }}</div>
                    <div class="mt-2 text-xs font-bold">{{ strtoupper($schedule_heading) }}</div>
                </div>

                <div class="absolute left-[1.6%] right-[1.6%] top-[26%] h-[42%] rounded border border-slate-300 bg-slate-50">
                    <div class="grid h-full grid-cols-12 gap-px p-2">
                        @for ($index = 0; $index < 48; $index++)
                            <div class="rounded-sm bg-white"></div>
                        @endfor
                    </div>
                </div>

                <template x-for="logo in logos" :key="logo.id">
                    <button
                        type="button"
                        x-show="logo.active"
                        x-on:mousedown.prevent="startDrag($event, logo.id)"
                        class="absolute border border-blue-300 bg-white/90 shadow-sm ring-2 ring-transparent hover:ring-blue-200"
                        :style="`left: ${logo.x}%; top: ${logo.y}%; width: ${logo.width}px;`"
                        :title="`Drag ${logo.label}`"
                    >
                        <img :src="logo.url" :alt="logo.label" class="block w-full object-contain">
                    </button>
                </template>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Logo</th>
                            <th class="px-3 py-2">Label</th>
                            <th class="px-3 py-2">Width</th>
                            <th class="px-3 py-2">Order</th>
                            <th class="px-3 py-2">Active</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($printLogos as $logo)
                            <tr wire:key="print-logo-{{ $logo->id }}">
                                <td class="px-3 py-2">
                                    <img src="{{ asset('storage/'.$logo->path) }}" alt="{{ $logo->label }}" class="h-10 w-16 object-contain">
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        value="{{ $logo->label }}"
                                        wire:change="updateLogo({{ $logo->id }}, 'label', $event.target.value)"
                                        class="w-44 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        min="24"
                                        max="240"
                                        value="{{ $logo->width }}"
                                        wire:change="updateLogo({{ $logo->id }}, 'width', $event.target.value)"
                                        class="w-24 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="number"
                                        min="1"
                                        max="99"
                                        value="{{ $logo->display_order }}"
                                        wire:change="updateLogo({{ $logo->id }}, 'display_order', $event.target.value)"
                                        class="w-20 rounded-md border border-slate-300 px-2 py-1.5 text-sm"
                                    >
                                </td>
                                <td class="px-3 py-2">
                                    <input
                                        type="checkbox"
                                        @checked($logo->is_active)
                                        wire:change="updateLogo({{ $logo->id }}, 'is_active', $event.target.checked)"
                                    >
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="deleteLogo({{ $logo->id }})" wire:confirm="Delete this logo?" class="rounded-md border border-red-200 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-slate-500">No logos uploaded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold">Assignatories</h3>
                    <p class="mt-1 text-sm text-slate-600">Purpose controls where the signature block appears in the printed schedule.</p>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Order</th>
                            <th class="px-3 py-2">Purpose</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Designation</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($signatories as $signatory)
                            <tr>
                                <td class="px-3 py-2">{{ $signatory->display_order }}</td>
                                <td class="px-3 py-2 font-medium">{{ $signatory->purpose }}</td>
                                <td class="px-3 py-2">{{ $signatory->person_name }}</td>
                                <td class="px-3 py-2">{{ $signatory->designation ?: '-' }}</td>
                                <td class="px-3 py-2">{{ $signatory->is_active ? 'Active' : 'Inactive' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <button wire:click="editSignatory({{ $signatory->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 hover:bg-slate-50">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-8 text-center text-slate-500">No assignatories configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</section>
