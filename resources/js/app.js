import './bootstrap';

import $ from 'jquery';
import select2 from 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.css';

window.$ = $;
window.jQuery = $;

select2(window, $);

const initPayrollEmployeePickers = () => {
    document.querySelectorAll('select[data-select2-employee-picker], select[data-select2-searchable]').forEach((select) => {
        const $select = $(select);

        if (!$.fn.select2) {
            return;
        }

        const isMultiple = select.multiple;

        if ($select.data('select2')) {
            $select.off('.payrollSelect2');
            $select.select2('destroy');
        }

        $select.select2({
            allowClear: !isMultiple,
            closeOnSelect: !isMultiple,
            placeholder: select.dataset.placeholder || (isMultiple ? 'Select employees' : 'Select option'),
            width: '100%',
        });

        $select.on('select2:select.payrollSelect2 select2:clear.payrollSelect2', () => {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        $select.on('change.payrollSelect2', () => {
            const componentRoot = select.closest('[wire\\:id]');
            const componentId = componentRoot?.getAttribute('wire:id');
            const model = select.dataset.model;
            const deferRequest = select.dataset.deferRequest === 'true';

            if (!componentId || !model || !window.Livewire?.find) {
                return;
            }

            window.Livewire.find(componentId).set(model, isMultiple ? ($select.val() || []) : ($select.val() || null), !deferRequest);
        });
    });
};

const initPayrollTableScrollbars = () => {
    document.querySelectorAll('.payroll-table-scroll').forEach((scrollArea) => {
        const table = scrollArea.querySelector('table');

        if (!table || scrollArea.classList.contains('hidden')) {
            return;
        }

        let scrollbar = scrollArea.querySelector(':scope > .payroll-floating-scrollbar');

        if (!scrollbar) {
            scrollbar = document.createElement('div');
            scrollbar.className = 'payroll-floating-scrollbar';
            scrollbar.setAttribute('aria-hidden', 'true');

            const inner = document.createElement('div');
            inner.className = 'payroll-floating-scrollbar-inner';
            scrollbar.appendChild(inner);
            scrollArea.prepend(scrollbar);
        }

        const inner = scrollbar.firstElementChild;
        const isScrollable = table.scrollWidth > scrollArea.clientWidth + 1;

        if (inner) {
            inner.style.width = `${table.scrollWidth}px`;
        }

        scrollbar.dataset.scrollable = isScrollable ? 'true' : 'false';

        if (scrollbar.dataset.bound === 'true') {
            return;
        }

        scrollbar.dataset.bound = 'true';

        let syncing = false;

        scrollbar.addEventListener('scroll', () => {
            if (syncing) {
                return;
            }

            syncing = true;
            scrollArea.scrollLeft = scrollbar.scrollLeft;
            window.requestAnimationFrame(() => {
                syncing = false;
            });
        });

        scrollArea.addEventListener('scroll', () => {
            if (syncing) {
                return;
            }

            syncing = true;
            scrollbar.scrollLeft = scrollArea.scrollLeft;
            window.requestAnimationFrame(() => {
                syncing = false;
            });
        });
    });
};

window.addEventListener('resize', initPayrollTableScrollbars);

(() => {
    let hooksInstalled = false;
    let activeRequests = 0;
    let progressTimer = null;
    let progressBar = null;

    const ensureProgressBar = () => {
        if (progressBar) {
            return progressBar;
        }

        progressBar = document.createElement('div');
        progressBar.setAttribute('aria-hidden', 'true');
        progressBar.style.position = 'fixed';
        progressBar.style.top = '0';
        progressBar.style.left = '0';
        progressBar.style.zIndex = '9999';
        progressBar.style.height = '3px';
        progressBar.style.width = '0';
        progressBar.style.background = '#2563eb';
        progressBar.style.boxShadow = '0 0 12px rgba(37, 99, 235, 0.55)';
        progressBar.style.opacity = '0';
        progressBar.style.transition = 'width 220ms ease, opacity 160ms ease';
        document.body.appendChild(progressBar);

        return progressBar;
    };

    const livewireControls = () => document.querySelectorAll([
        '[wire\\:id] button',
        '[wire\\:id] input',
        '[wire\\:id] select',
        '[wire\\:id] textarea',
    ].join(','));

    const setControlsDisabled = (disabled) => {
        livewireControls().forEach((control) => {
            if (control.type === 'hidden') {
                return;
            }

            if (disabled) {
                if (!control.dataset.busyWasDisabled) {
                    control.dataset.busyWasDisabled = control.disabled ? 'true' : 'false';
                }

                control.disabled = true;
                control.setAttribute('aria-busy', 'true');
            } else {
                if (control.dataset.busyWasDisabled === 'false') {
                    control.disabled = false;
                }

                delete control.dataset.busyWasDisabled;
                control.removeAttribute('aria-busy');
            }
        });
    };

    const beginProgress = () => {
        const bar = ensureProgressBar();

        window.clearInterval(progressTimer);
        bar.style.opacity = '1';
        bar.style.width = '18%';

        progressTimer = window.setInterval(() => {
            const currentWidth = Number.parseFloat(bar.style.width) || 0;
            const nextWidth = Math.min(currentWidth + Math.max(2, (92 - currentWidth) * 0.12), 92);
            bar.style.width = `${nextWidth}%`;
        }, 260);
    };

    const finishProgress = () => {
        const bar = ensureProgressBar();

        window.clearInterval(progressTimer);
        bar.style.width = '100%';

        window.setTimeout(() => {
            if (activeRequests > 0) {
                return;
            }

            bar.style.opacity = '0';
            bar.style.width = '0';
        }, 220);
    };

    const startLoading = () => {
        activeRequests += 1;

        if (activeRequests === 1) {
            document.body.style.cursor = 'progress';
            document.body.setAttribute('aria-busy', 'true');
            setControlsDisabled(true);
            beginProgress();
        }
    };

    const stopLoading = () => {
        activeRequests = Math.max(0, activeRequests - 1);

        if (activeRequests === 0) {
            document.body.style.cursor = '';
            document.body.removeAttribute('aria-busy');
            setControlsDisabled(false);
            finishProgress();
        }
    };

    const installLivewireHooks = () => {
        if (hooksInstalled || !window.Livewire?.hook) {
            return;
        }

        hooksInstalled = true;

        window.Livewire.hook('request', ({ succeed, fail }) => {
            startLoading();
            succeed(() => {
                stopLoading();
                queueMicrotask(() => {
                    initPayrollEmployeePickers();
                    initPayrollTableScrollbars();
                });
            });
            fail(stopLoading);
        });

        window.Livewire.hook('commit', ({ succeed, fail }) => {
            startLoading();
            succeed(() => {
                stopLoading();
                queueMicrotask(() => {
                    initPayrollEmployeePickers();
                    initPayrollTableScrollbars();
                });
            });
            fail(stopLoading);
        });
    };

    document.addEventListener('livewire:init', () => {
        installLivewireHooks();
        initPayrollEmployeePickers();
        initPayrollTableScrollbars();
    });
    document.addEventListener('livewire:initialized', () => {
        installLivewireHooks();
        initPayrollEmployeePickers();
        initPayrollTableScrollbars();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            installLivewireHooks();
            initPayrollEmployeePickers();
            initPayrollTableScrollbars();
        });
    } else {
        installLivewireHooks();
        initPayrollEmployeePickers();
        initPayrollTableScrollbars();
    }
})();
