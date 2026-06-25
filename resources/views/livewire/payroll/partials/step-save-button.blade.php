<button
    type="button"
    x-on:click="saveStep()"
    wire:loading.attr="disabled"
    wire:target="saveStepChanges"
    class="rounded-md border border-[#696cff] bg-white px-4 py-2 text-sm font-medium text-[#5f61e6] hover:bg-[#f1f2ff] disabled:cursor-wait disabled:opacity-60"
>
    <span wire:loading.remove wire:target="saveStepChanges">Save as Draft</span>
    <span wire:loading wire:target="saveStepChanges">Saving Draft...</span>
</button>
