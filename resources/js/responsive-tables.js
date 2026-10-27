// Enhance the existing rows rather than duplicate forms, IDs, or Livewire actions.
export function enhanceResponsiveTables(root = document) {
    root.querySelectorAll('.erp-body table').forEach((table) => {
        const headerRows = [...(table.tHead?.rows || [])];
        if (!headerRows.length) return;

        const grid = [];
        headerRows.forEach((row, rowIndex) => {
            grid[rowIndex] ||= [];
            let column = 0;
            [...row.cells].forEach(cell => {
                while (grid[rowIndex][column] !== undefined) column++;
                const label = cell.dataset.cardLabel || cell.textContent.replace(/\s+/g, ' ').trim()
                    || (cell.querySelector('input[type="checkbox"]') ? 'Select' : 'Actions');
                for (let y = rowIndex; y < rowIndex + cell.rowSpan; y++) {
                    grid[y] ||= [];
                    for (let x = column; x < column + cell.colSpan; x++) grid[y][x] = label;
                }
                column += cell.colSpan;
            });
        });
        const labels = Array.from({ length: Math.max(...grid.map(row => row.length)) }, (_, column) =>
            [...new Set(grid.map(row => row[column]).filter(Boolean))].join(' / '));
        const identity = labels.findIndex(label => /(?:^|\/\s*)(employee name|full name|name|title)\b/i.test(label));
        const fallbackIdentity = labels.findIndex(label => /^(employee|document|program|date|period|reference)\b/i.test(label));
        const headingColumn = identity >= 0 ? identity : (fallbackIdentity >= 0 ? fallbackIdentity : labels.findIndex(label => !['Select', 'Actions', '#'].includes(label)));

        table.classList.add('erp-card-table');
        table.setAttribute('role', 'table');
        [...table.tBodies, table.tFoot].filter(Boolean).forEach(group => {
            group.setAttribute('role', 'rowgroup');
            // Livewire's default loading stylesheet must win over our grid
            // display. While loading, Livewire supplies an inline display value.
            const loadingOnly = [...group.attributes].some(attribute => {
                const parts = attribute.name.split('.');
                return parts[0] === 'wire:loading' && !parts.some(part => ['remove', 'class', 'attr'].includes(part));
            });
            group.toggleAttribute('data-card-loading', loadingOnly);
            const occupied = [];
            [...group.rows].forEach((row, rowIndex) => {
                row.setAttribute('role', 'row');
                const inherited = (occupied[rowIndex] || []).filter(Boolean);
                const context = [...new Set(inherited.map(cell => `${cell.label}: ${cell.value}`))].join(' · ');
                if (context) row.dataset.cardContext = context;
                else delete row.dataset.cardContext;
                let column = 0;
                [...row.cells].forEach(cell => {
                    while (occupied[rowIndex]?.[column]) column++;
                    const label = labels[column] || 'Details';
                    cell.dataset.cardLabel = label;
                    cell.toggleAttribute('data-card-heading', column === headingColumn && cell.colSpan === 1 && group !== table.tFoot);
                    cell.toggleAttribute('data-card-wide', cell.colSpan > 1 || /^(details|actions?|remarks|description|select)$/i.test(label));
                    cell.toggleAttribute('data-card-empty', group === table.tFoot && !cell.textContent.trim() && !cell.querySelector('input, button, select'));
                    cell.setAttribute('role', cell.tagName === 'TH' ? 'rowheader' : 'cell');
                    const span = cell.getAttribute('rowspan') === '0' ? group.rows.length - rowIndex : cell.rowSpan;
                    for (let y = rowIndex + 1; y < rowIndex + span; y++) {
                        occupied[y] ||= [];
                        for (let x = column; x < column + cell.colSpan; x++) {
                            occupied[y][x] = { label, value: cell.textContent.replace(/\s+/g, ' ').trim() };
                        }
                    }
                    column += cell.colSpan;
                });
            });
        });
    });
}

let scheduled = false;
// Delegate to keep the original checkbox/Livewire binding and survive row morphs.
document.addEventListener('click', event => {
    if (!window.matchMedia('(max-width: 1023px)').matches || event.defaultPrevented) return;
    const row = event.target.closest?.('.erp-card-table tr[data-card-selectable]');
    if (!row) return;
    const editor = event.target.closest('[data-card-editor]');
    if (editor) {
        // Clicking the field itself keeps the native interaction. The cell's
        // label and padding also open its selector without selecting the card.
        if (event.target.closest('select, input, button, a, label, textarea, option')) return;
        const select = editor.querySelector('select:not(:disabled)');
        if (select) {
            select.focus();
            try {
                if (typeof select.showPicker === 'function') select.showPicker();
                else select.click();
            } catch {
                select.click();
            }
        }
        return;
    }
    if (event.target.closest('input, select, textarea, button, a, label, [role="button"], [contenteditable="true"]')) return;
    if (window.getSelection()?.toString()) return;
    row.querySelector('[data-card-selection] input[type="checkbox"]:not(:disabled)')?.click();
});

function scheduleEnhancement() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        scheduled = false;
        enhanceResponsiveTables();
    });
}

function start() {
    enhanceResponsiveTables();
    // Livewire replaces both headers and rows after filtering, paging and saving.
    new MutationObserver(records => {
        if (records.some(record => record.target.closest?.('.erp-body'))) scheduleEnhancement();
    }).observe(document.body, { childList: true, subtree: true });
}

document.addEventListener('livewire:init', () => {
    window.Livewire?.hook('morphed', scheduleEnhancement);
}, { once: true });

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
else start();
document.addEventListener('livewire:navigated', scheduleEnhancement);
