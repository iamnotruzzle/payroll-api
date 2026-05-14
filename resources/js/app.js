import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
            succeed(stopLoading);
            fail(stopLoading);
        });

        window.Livewire.hook('commit', ({ succeed, fail }) => {
            startLoading();
            succeed(stopLoading);
            fail(stopLoading);
        });
    };

    document.addEventListener('livewire:init', installLivewireHooks);
    document.addEventListener('livewire:initialized', installLivewireHooks);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installLivewireHooks);
    } else {
        installLivewireHooks();
    }
})();
