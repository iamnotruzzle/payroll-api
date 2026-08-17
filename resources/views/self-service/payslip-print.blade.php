<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay Slip · {{ $batch?->payroll_period ?? $record->id }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { background: #0f172a; color: #e2e8f0; font-family: Arial, Helvetica, sans-serif; }
        .actions { position: sticky; top: 0; z-index: 2; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center; padding: 10px 12px; background: #0f172a; border-bottom: 1px solid #1e293b; }
        .actions a, .actions button { border: 1px solid #94a3b8; border-radius: 4px; background: #fff; color: #0f172a; padding: 7px 14px; text-decoration: none; cursor: pointer; font-size: 13px; }
        .actions button.secondary { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        .meta { margin-right: 8px; color: #94a3b8; font-size: 12px; }
        .status { min-height: 1.2em; color: #fbbf24; font-size: 12px; }
        .viewer { height: calc(100% - 52px); overflow: auto; background: #525659; padding: 16px 0 32px; }
        #pages { display: flex; flex-direction: column; align-items: center; gap: 16px; }
        #pages canvas { display: block; max-width: calc(100% - 24px); height: auto; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,.35); }
        @media print { .actions, .status { display: none !important; } body { background: #fff; } .viewer { height: auto; overflow: visible; background: #fff; padding: 0; } #pages { gap: 0; } #pages canvas { max-width: 100%; box-shadow: none; page-break-after: always; } }
    </style>
</head>
<body>
<div class="actions">
    <span class="meta">Pay Slip · {{ $batch?->payroll_period ?? 'Payroll' }}</span>
    <a href="{{ $backUrl }}">Back</a>
    <button type="button" id="btn-print" disabled>Print</button>
    <button type="button" class="secondary" id="btn-open" disabled>Open PDF</button>
    <span class="status" id="status">Loading payslip…</span>
</div>
<div class="viewer"><div id="pages"></div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    const pdfBase64 = @json($pdfBase64);
    const statusEl = document.getElementById('status');
    const pagesEl = document.getElementById('pages');
    const btnPrint = document.getElementById('btn-print');
    const btnOpen = document.getElementById('btn-open');
    let blobUrl = null;
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function bytes(base64) {
        const binary = atob(base64);
        const result = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) result[i] = binary.charCodeAt(i);
        return result;
    }

    async function load() {
        try {
            const data = bytes(pdfBase64);
            if (!data.length) throw new Error('Payslip PDF is empty.');
            blobUrl = URL.createObjectURL(new Blob([data], { type: 'application/pdf' }));
            const pdf = await pdfjsLib.getDocument({ data }).promise;
            const ratio = Math.min(window.devicePixelRatio || 1, 3);
            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                const page = await pdf.getPage(pageNumber);
                const viewport = page.getViewport({ scale: 1.5 });
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d', { alpha: false });
                canvas.width = Math.floor(viewport.width * ratio);
                canvas.height = Math.floor(viewport.height * ratio);
                canvas.style.width = Math.floor(viewport.width) + 'px';
                canvas.style.height = Math.floor(viewport.height) + 'px';
                pagesEl.appendChild(canvas);
                await page.render({ canvasContext: context, viewport, transform: ratio === 1 ? null : [ratio, 0, 0, ratio, 0, 0] }).promise;
            }
            btnPrint.disabled = false;
            btnOpen.disabled = false;
            statusEl.textContent = '';
        } catch (error) {
            console.error(error);
            statusEl.textContent = error.message || 'Failed to load payslip.';
        }
    }

    btnPrint.addEventListener('click', function () {
        if (!blobUrl) return;
        let frame = document.getElementById('print-frame');
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = 'print-frame';
            frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
        }
        frame.onload = function () { setTimeout(function () { frame.contentWindow.focus(); frame.contentWindow.print(); }, 400); };
        frame.src = blobUrl;
    });
    btnOpen.addEventListener('click', function () { if (blobUrl) window.open(blobUrl, '_blank', 'noopener'); });
    load();
})();
</script>
</body>
</html>
