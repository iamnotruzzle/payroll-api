# Reference projects

The `reference projects/` directory holds external/sibling codebases for comparison only.

- Read from it when useful to understand patterns or prior implementations.
- Do not modify, create, delete, move, or commit anything under `reference projects/`.
- Implement needed changes in this repository (payroll-api), adapting patterns as appropriate.

# Frontend overlays

Use Alpine.js modals and drawers that are present on first browser load. Do not inject overlay markup with a Livewire `@if` re-render, and do not use Livewire `@teleport`.

- Small forms: `<x-setup-form-modal name="adjustment-type" title="New …" edit-title="Edit …">`
- Larger forms: `<x-setup-form-drawer name="deduction-program" title="New …" edit-title="Edit …">`
- Always render the overlay component. Visibility is Alpine `x-show` + `x-teleport="body"`.
- Open and close must not trigger Livewire requests. Use `erpOverlay.open($wire, 'name', { …fields }, editing)` and `erpOverlay.close('name')`.
- Livewire is only for save, delete, and real data loads (for example salary-schedule `load`). After a successful save, dispatch `erp-overlay-close`.
