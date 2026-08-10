<div class="space-y-4">
    @if (session('docs_status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('docs_status') }}</div>
    @endif

    @unless ($usesV2)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Document uploads require <code class="rounded bg-white px-1">HRIS_USE_V2=true</code>.
        </div>
    @endunless

    @if ($canManage && $usesV2)
        <form wire:submit="save" class="space-y-3 rounded-md border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Title</label>
                    <input wire:model="title" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm" placeholder="e.g. PDS scanned copy">
                    @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-sm font-medium">Category</label>
                    <select wire:model="category" class="mt-1 w-full rounded-md border border-slate-300 py-2 pl-3 pr-8 text-sm">
                        <option value="general">General</option>
                        <option value="pds">PDS</option>
                        <option value="id">Government ID</option>
                        <option value="certificate">Certificate</option>
                        <option value="contract">Contract</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">File</label>
                    <input wire:model="upload" type="file" class="mt-1 block w-full text-sm">
                    @error('upload') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            <button type="submit" class="rounded-md bg-[#696cff] px-4 py-2 text-sm font-medium text-white hover:bg-[#5f61e6]">Upload</button>
            <div wire:loading wire:target="upload,save" class="text-xs text-slate-500">Working…</div>
        </form>
    @endif

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Document</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Uploaded</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($documents as $document)
                    <tr wire:key="doc-{{ $document->id }}">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ $document->title }}</p>
                            <p class="text-xs text-slate-500">{{ $document->original_name }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $document->category }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ optional($document->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('employees.documents.download', [$empId, $document->id]) }}"
                               class="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium hover:bg-slate-50">Download</a>
                            @if ($canManage)
                                <button type="button" wire:click="deleteDocument({{ $document->id }})" wire:confirm="Delete this document?"
                                        class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No documents uploaded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
