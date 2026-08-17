import './bootstrap';

import $ from 'jquery';
import select2 from 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.css';
import { gsap } from 'gsap';

window.$ = $;
window.jQuery = $;

select2(window, $);

window.__erpOverlayState = window.__erpOverlayState || {};

window.erpOverlay = {
    getState(name) {
        window.__erpOverlayState[name] ??= { open: false, editing: false, pristine: true };

        return window.__erpOverlayState[name];
    },

    fill($wire, values = {}) {
        if (!$wire || typeof $wire.$set !== 'function') {
            return;
        }

        Object.entries(values).forEach(([key, value]) => {
            $wire.$set(key, value, false);
        });
    },

    open($wire, name, values = {}, editing = false) {
        this.fill($wire, values);
        const state = this.getState(name);
        state.open = true;
        state.editing = Boolean(editing);
        state.pristine = true;
        window.dispatchEvent(new CustomEvent('erp-overlay-open', { detail: { name, editing: state.editing } }));
    },

    close(name = null) {
        if (name) {
            this.getState(name).open = false;
        } else {
            Object.keys(window.__erpOverlayState).forEach((key) => {
                this.getState(key).open = false;
            });
        }

        window.dispatchEvent(new CustomEvent('erp-overlay-close', { detail: { name } }));
    },
};

const initThemeToggle = () => {
    const root = document.documentElement;
    const update = () => {
        const isDark = root.dataset.theme === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
            button.setAttribute('aria-pressed', String(isDark));
        });
    };

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        if (button.dataset.bound) return;
        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('erp-theme', root.dataset.theme);
            update();
        });
    });
    update();
};

const initPortalMotion = () => {
    const portal = document.querySelector('.erp-portal-body');

    if (!portal || portal.dataset.motionBound === 'true') {
        return;
    }

    portal.dataset.motionBound = 'true';

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const ambientAnimations = [];
    const context = gsap.context(() => {
        const revealItems = portal.querySelectorAll('[data-portal-reveal], [data-portal-signin]');
        const system = portal.querySelector('[data-portal-system]');

        gsap.from(revealItems, {
            autoAlpha: 0,
            y: 14,
            duration: 1.05,
            stagger: 0.16,
            ease: 'power2.out',
            clearProps: 'opacity,visibility,transform',
        });

        if (system && window.getComputedStyle(system).display !== 'none') {
            gsap.from('[data-portal-system-node]', {
                autoAlpha: 0,
                scale: 0.94,
                duration: 0.9,
                stagger: 0.13,
                delay: 0.38,
                ease: 'power2.out',
                clearProps: 'opacity,visibility,transform',
            });

            ambientAnimations.push(
                gsap.to('[data-portal-orbit]', {
                    rotate: (index) => index % 2 === 0 ? 360 : -360,
                    duration: (index) => index % 2 === 0 ? 56 : 44,
                    repeat: -1,
                    ease: 'none',
                    transformOrigin: '50% 50%',
                }),
                gsap.to('[data-portal-pulse]', {
                    scale: 1.35,
                    autoAlpha: 0.52,
                    duration: 2.8,
                    stagger: 0.42,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                }),
            );
        }
    }, portal);

    const syncAmbientMotion = () => {
        ambientAnimations.forEach((animation) => animation.paused(document.hidden));
    };
    const cleanupPortalMotion = () => {
        document.removeEventListener('visibilitychange', syncAmbientMotion);
        context.revert();
    };

    document.addEventListener('visibilitychange', syncAmbientMotion);
    window.addEventListener('pagehide', cleanupPortalMotion, { once: true });
};

const initLauncherMotion = () => {
    const launcher = document.querySelector('[data-launcher-root]');

    if (!launcher || launcher.dataset.motionBound === 'true') {
        return;
    }

    launcher.dataset.motionBound = 'true';

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    const ambientAnimations = [];
    const context = gsap.context(() => {
        const intro = launcher.querySelector('[data-launcher-intro]');
        const groups = launcher.querySelectorAll('[data-launcher-group]');
        const cards = launcher.querySelectorAll('[data-launcher-card]');

        gsap.from(intro, {
            autoAlpha: 0,
            y: 18,
            filter: 'blur(5px)',
            duration: 1.15,
            ease: 'power3.out',
            clearProps: 'opacity,visibility,transform,filter',
        });

        gsap.from(groups, {
            autoAlpha: 0,
            y: 16,
            duration: 1.05,
            stagger: 0.2,
            delay: 0.22,
            ease: 'power2.out',
            clearProps: 'opacity,visibility,transform',
        });

        gsap.from(cards, {
            autoAlpha: 0,
            y: 14,
            duration: 1,
            stagger: 0.09,
            delay: 0.42,
            ease: 'power2.out',
            clearProps: 'opacity,visibility,transform',
        });

        launcher.querySelectorAll('[data-launcher-orbit]').forEach((orbit, index) => {
            ambientAnimations.push(gsap.to(orbit, {
                rotate: index % 2 === 0 ? 360 : -360,
                duration: index % 2 === 0 ? 72 : 60,
                repeat: -1,
                ease: 'none',
                transformOrigin: '50% 50%',
            }));
        });

        launcher.querySelectorAll('[data-launcher-signal]').forEach((signal, index) => {
            ambientAnimations.push(gsap.fromTo(signal, {
                x: 0,
            }, {
                x: () => Math.max(0, signal.parentElement.clientWidth - signal.offsetWidth),
                duration: 11 + (index * 2),
                delay: index * 1.8,
                repeat: -1,
                repeatDelay: 1.4,
                ease: 'none',
            }));
        });

        ambientAnimations.push(gsap.to('[data-launcher-haze]', {
            x: -16,
            y: 12,
            scale: 1.06,
            duration: 18,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        }));
    }, launcher);

    const syncAmbientMotion = () => {
        ambientAnimations.forEach((animation) => animation.paused(document.hidden));
    };
    const cleanupLauncherMotion = () => {
        document.removeEventListener('visibilitychange', syncAmbientMotion);
        context.revert();
    };

    document.addEventListener('visibilitychange', syncAmbientMotion);
    window.addEventListener('pagehide', cleanupLauncherMotion, { once: true });
};

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
            const payrollSidebar = select.closest('.payroll-generation-sidebar');
            if (payrollSidebar) {
                payrollSidebar.scrollLeft = 0;
            }

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

window.initPayrollEmployeePickers = initPayrollEmployeePickers;

const filterHistoricalDepartments = (row) => {
    const division = row.querySelector('select[data-historical-division]');
    const department = row.querySelector('select[data-historical-department]');
    if (!division || !department) return;

    const divisionId = String(division.value || '');
    let selectedIsValid = !department.value;
    department.querySelectorAll('option[data-division-id]').forEach((option) => {
        const available = !divisionId || option.dataset.divisionId === divisionId;
        option.disabled = !available;
        option.hidden = !available;
        if (option.selected && available) selectedIsValid = true;
    });

    if (!selectedIsValid) {
        department.value = '';
        department.dispatchEvent(new Event('change', { bubbles: true }));
    } else if (window.jQuery) {
        window.jQuery(department).trigger('change.select2');
    }
};

const initHistoricalOrganizationPickers = () => {
    document.querySelectorAll('[data-historical-org-row]').forEach(filterHistoricalDepartments);
};

document.addEventListener('change', (event) => {
    const division = event.target.closest?.('select[data-historical-division]');
    if (division) {
        filterHistoricalDepartments(division.closest('[data-historical-org-row]'));
        return;
    }

    const department = event.target.closest?.('select[data-historical-department]');
    if (!department || !department.value) return;
    const row = department.closest('[data-historical-org-row]');
    const divisionSelect = row?.querySelector('select[data-historical-division]');
    const selectedOption = department.selectedOptions?.[0];
    const divisionId = selectedOption?.dataset.divisionId;
    if (!divisionSelect || !divisionId || String(divisionSelect.value) === String(divisionId)) return;

    divisionSelect.value = String(divisionId);
    divisionSelect.dispatchEvent(new Event('change', { bubbles: true }));
    if (window.jQuery) window.jQuery(divisionSelect).trigger('change.select2');
});

window.initHistoricalOrganizationPickers = initHistoricalOrganizationPickers;

// Masterlist reference drawers use delegated native events so opening remains
// instant and reliable after Livewire replaces the staged preview DOM.
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-masterlist-open]');
    if (opener) {
        const component = opener.closest('[wire\\:id]');
        const type = opener.dataset.masterlistOpen;
        const drawer = component?.querySelector(`[data-masterlist-drawer="${type}"]`);
        const form = drawer?.querySelector('form');
        if (!drawer || !form) return;

        form.reset();
        if (type === 'position') {
            form.elements.source.value = opener.dataset.source || '';
            form.elements.title.value = opener.dataset.source || '';
            form.elements.salary_grade.value = opener.dataset.salaryGrade || '';
            form.elements.remarks.value = `Masterlist import #${opener.dataset.importId || ''}`;
            drawer.querySelector('[data-masterlist-source-label]').textContent = opener.dataset.source || '';
        } else {
            const division = opener.dataset.division || '';
            const department = opener.dataset.department || '';
            form.elements.source_division.value = division;
            form.elements.source_department.value = department;
            form.elements.division_name.value = division;
            form.elements.department_name.value = department;
            drawer.querySelector('[data-masterlist-source-label]').textContent = `${division} → ${department}`;

            const matchingOption = Array.from(form.elements.division_id.options)
                .find((option) => (option.dataset.divisionName || '').trim().toLowerCase() === division.trim().toLowerCase());
            form.elements.division_id.value = matchingOption?.value || '';
            drawer.querySelector('[data-masterlist-new-division]').hidden = Boolean(matchingOption);
        }

        drawer.hidden = false;
        document.body.classList.add('overflow-hidden');
        form.querySelector('input:not([type="hidden"]), select')?.focus();
        return;
    }

    const closer = event.target.closest('[data-masterlist-close]');
    if (closer) {
        const drawer = closer.closest('[data-masterlist-drawer]');
        if (drawer) drawer.hidden = true;
        document.body.classList.remove('overflow-hidden');
    }
});

document.addEventListener('change', (event) => {
    if (!event.target.matches('[data-masterlist-division-select]')) return;
    const drawer = event.target.closest('[data-masterlist-drawer]');
    const fields = drawer?.querySelector('[data-masterlist-new-division]');
    if (fields) fields.hidden = Boolean(event.target.value);
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-masterlist-submit]');
    if (!form) return;
    event.preventDefault();
    if (!form.reportValidity()) return;

    const component = form.closest('[wire\\:id]');
    const componentId = component?.getAttribute('wire:id');
    const livewire = componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    if (!livewire) return;

    const button = form.querySelector('button[type="submit"], button:not([type])');
    const payload = Object.fromEntries(new FormData(form).entries());
    if (payload.division_id === '') payload.division_id = null;
    button?.setAttribute('disabled', 'disabled');
    try {
        const method = form.dataset.masterlistSubmit === 'position'
            ? 'createPositionFromBrowser'
            : 'createDepartmentFromBrowser';
        await livewire.call(method, payload);
        document.body.classList.remove('overflow-hidden');
    } finally {
        button?.removeAttribute('disabled');
    }
});

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
                    initHistoricalOrganizationPickers();
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
                    initHistoricalOrganizationPickers();
                    initPayrollTableScrollbars();
                });
            });
            fail(stopLoading);
        });
    };

    document.addEventListener('livewire:init', () => {
        installLivewireHooks();
        initPayrollEmployeePickers();
        initHistoricalOrganizationPickers();
        initPayrollTableScrollbars();
    });
    document.addEventListener('livewire:initialized', () => {
        installLivewireHooks();
        initPayrollEmployeePickers();
        initHistoricalOrganizationPickers();
        initPayrollTableScrollbars();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initThemeToggle();
            initPortalMotion();
            initLauncherMotion();
            installLivewireHooks();
            initPayrollEmployeePickers();
            initHistoricalOrganizationPickers();
            initPayrollTableScrollbars();
        });
    } else {
        initThemeToggle();
        initPortalMotion();
        initLauncherMotion();
        installLivewireHooks();
        initPayrollEmployeePickers();
        initHistoricalOrganizationPickers();
        initPayrollTableScrollbars();
    }
})();
