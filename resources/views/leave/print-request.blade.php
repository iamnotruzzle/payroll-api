<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Leave Application #{{ $leave->leave_id }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { background: #0f172a; color: #e2e8f0; font-family: Arial, Helvetica, sans-serif; }
        .actions {
            position: sticky; top: 0; z-index: 2;
            display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: center;
            padding: 10px 12px; background: #0f172a; border-bottom: 1px solid #1e293b;
        }
        .actions a, .actions button {
            border: 1px solid #94a3b8; background: #fff; color: #0f172a;
            padding: 7px 14px; border-radius: 4px; text-decoration: none; cursor: pointer; font-size: 13px;
        }
        .actions button.secondary { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        .meta { font-size: 12px; color: #94a3b8; margin-right: 8px; }
        .status { font-size: 12px; color: #fbbf24; min-height: 1.2em; }
        .viewer {
            height: calc(100% - 52px);
            overflow: auto;
            background: #525659;
            padding: 16px 0 32px;
        }
        #pages {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        #pages canvas {
            display: block;
            max-width: calc(100% - 24px);
            height: auto;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.35);
        }
        @media print {
            .actions, .status { display: none !important; }
            body { background: #fff; }
            .viewer { height: auto; overflow: visible; background: #fff; padding: 0; }
            #pages { gap: 0; }
            #pages canvas { max-width: 100%; box-shadow: none; page-break-after: always; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <span class="meta">CSC Form 6 · Leave #{{ $leave->leave_id }} · {{ $leave->employee?->full_name ?: $leave->emp_id }}</span>
        <a href="{{ $backUrl }}">Back</a>
        <button type="button" id="btn-print" disabled>Print</button>
        <button type="button" class="secondary" id="btn-open" disabled>Open in new tab</button>
        <span class="status" id="status">Loading form…</span>
    </div>
    <div class="viewer">
        <div id="pages"></div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (function () {
            // PDF bytes are embedded in this HTML page — no /print/file request for IDM to steal.
            const pdfBase64 = @json($pdfBase64);
            const statusEl = document.getElementById('status');
            const pagesEl = document.getElementById('pages');
            const btnPrint = document.getElementById('btn-print');
            const btnOpen = document.getElementById('btn-open');
            let blobUrl = null;

            pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

            function setStatus(text) {
                statusEl.textContent = text || '';
            }

            function base64ToUint8Array(base64) {
                const binary = atob(base64);
                const bytes = new Uint8Array(binary.length);
                for (let i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                return bytes;
            }

            async function load() {
                try {
                    if (!pdfBase64) {
                        throw new Error('Leave form data is missing.');
                    }

                    const bytes = base64ToUint8Array(pdfBase64);
                    if (!bytes.length) {
                        throw new Error('Leave form data is empty.');
                    }

                    const blob = new Blob([bytes], { type: 'application/pdf' });
                    blobUrl = URL.createObjectURL(blob);

                    const pdf = await pdfjsLib.getDocument({ data: bytes }).promise;
                    setStatus('Rendering…');

                    const pixelRatio = Math.min(window.devicePixelRatio || 1, 3);
                    const baseScale = 1.5;

                    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const viewport = page.getViewport({ scale: baseScale });
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d', { alpha: false });

                        // Render at device pixel ratio so HiDPI screens stay sharp.
                        canvas.width = Math.floor(viewport.width * pixelRatio);
                        canvas.height = Math.floor(viewport.height * pixelRatio);
                        canvas.style.width = Math.floor(viewport.width) + 'px';
                        canvas.style.height = Math.floor(viewport.height) + 'px';

                        const transform = pixelRatio !== 1
                            ? [pixelRatio, 0, 0, pixelRatio, 0, 0]
                            : null;

                        pagesEl.appendChild(canvas);
                        await page.render({
                            canvasContext: context,
                            viewport,
                            transform,
                        }).promise;
                    }

                    btnPrint.disabled = false;
                    btnOpen.disabled = false;
                    setStatus('');
                } catch (err) {
                    console.error(err);
                    setStatus(err.message || 'Failed to load leave form.');
                }
            }

            function printPdfBlob() {
                if (!blobUrl) return;

                // Print the real PDF (1 page), not the HTML canvas preview (which can spill to 2 sheets).
                let frame = document.getElementById('print-frame');
                if (!frame) {
                    frame = document.createElement('iframe');
                    frame.id = 'print-frame';
                    frame.title = 'Print leave form';
                    frame.setAttribute('aria-hidden', 'true');
                    frame.style.position = 'fixed';
                    frame.style.right = '0';
                    frame.style.bottom = '0';
                    frame.style.width = '0';
                    frame.style.height = '0';
                    frame.style.border = '0';
                    document.body.appendChild(frame);
                }

                const triggerPrint = function () {
                    try {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } catch (err) {
                        // Fallback: open the PDF for the user to print manually.
                        window.open(blobUrl, '_blank', 'noopener');
                    }
                };

                frame.onload = function () {
                    // Chromium needs a short delay after the PDF plugin loads.
                    setTimeout(triggerPrint, 400);
                };
                frame.src = blobUrl;
            }

            btnPrint.addEventListener('click', printPdfBlob);

            btnOpen.addEventListener('click', function () {
                if (!blobUrl) return;
                window.open(blobUrl, '_blank', 'noopener');
            });

            load();
        })();
    </script>
</body>
</html>
