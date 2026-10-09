let pdfjsPromise = null;

export function loadPdfjs() {
    pdfjsPromise ??= Promise.all([
        import('pdfjs-dist'),
        import('pdfjs-dist/build/pdf.worker.min.mjs?url'),
    ]).then(([pdfjs, worker]) => {
        pdfjs.GlobalWorkerOptions.workerSrc = worker.default;
        return pdfjs;
    });

    return pdfjsPromise;
}

/**
 * @param {string|ArrayBuffer|Uint8Array} source URL (same-origin, cookies sent) or raw bytes
 */
export async function openPdf(source) {
    const pdfjs = await loadPdfjs();
    const params = typeof source === 'string'
        ? { url: source, withCredentials: true }
        : { data: source instanceof Uint8Array ? source : new Uint8Array(source) };

    return pdfjs.getDocument({ ...params, isEvalSupported: false }).promise;
}

/**
 * Renders only the pages near the viewport (visible pages ± buffer) and frees the canvas of
 * everything else, so a 50-page contract does not exhaust a phone's canvas memory.
 * Page wrappers must carry data-page="N" and already have their final size (aspect-ratio).
 */
export class VirtualPages {
    constructor(pdf, { buffer = 1, root = null } = {}) {
        this.pdf = pdf;
        this.buffer = buffer;
        this.root = root;
        this.wrappers = new Map();
        this.visible = new Set();
        this.rendered = new Map();
        this.observer = null;
        this.resizeObserver = null;
        this.resizeTimer = null;
    }

    observe(wrappers) {
        this.disconnect();
        wrappers.forEach((el) => this.wrappers.set(Number(el.dataset.page), el));

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const page = Number(entry.target.dataset.page);
                entry.isIntersecting ? this.visible.add(page) : this.visible.delete(page);
            });
            this.update();
        }, { root: this.root, rootMargin: '200px 0px' });

        this.wrappers.forEach((el) => this.observer.observe(el));

        let lastWidth = null;
        this.resizeObserver = new ResizeObserver(() => {
            const first = this.wrappers.values().next().value;
            const width = first ? first.clientWidth : 0;
            if (width === lastWidth) return;
            lastWidth = width;
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => this.rerenderAll(), 200);
        });
        const first = this.wrappers.values().next().value;
        if (first) this.resizeObserver.observe(first);
    }

    update() {
        if (this.visible.size === 0) return;

        const min = Math.max(1, Math.min(...this.visible) - this.buffer);
        const max = Math.min(this.pdf.numPages, Math.max(...this.visible) + this.buffer);

        for (const page of [...this.rendered.keys()]) {
            if (page < min || page > max) this.release(page);
        }
        for (let page = min; page <= max; page++) {
            if (!this.rendered.has(page)) this.render(page);
        }
    }

    async render(pageNumber) {
        const wrapper = this.wrappers.get(pageNumber);
        if (!wrapper) return;

        const entry = { canvas: null, task: null, cancelled: false };
        this.rendered.set(pageNumber, entry);

        try {
            const page = await this.pdf.getPage(pageNumber);
            if (entry.cancelled) return;

            const base = page.getViewport({ scale: 1 });
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            const scale = (wrapper.clientWidth / base.width) * ratio;
            const viewport = page.getViewport({ scale });

            const canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.className = 'absolute inset-0 w-full h-full pointer-events-none select-none';
            canvas.setAttribute('aria-hidden', 'true');
            entry.canvas = canvas;

            entry.task = page.render({ canvasContext: canvas.getContext('2d'), viewport });
            await entry.task.promise;
            if (entry.cancelled) return;

            wrapper.querySelector('[data-canvas-slot]')?.replaceChildren(canvas);
            wrapper.dataset.rendered = 'true';
        } catch (error) {
            if (error?.name !== 'RenderingCancelledException') {
                console.error(`Gagal merender halaman ${pageNumber}`, error);
                wrapper.dataset.renderError = 'true';
            }
        }
    }

    release(pageNumber) {
        const entry = this.rendered.get(pageNumber);
        if (!entry) return;

        entry.cancelled = true;
        entry.task?.cancel();
        if (entry.canvas) {
            entry.canvas.width = 0;
            entry.canvas.height = 0;
            entry.canvas.remove();
        }
        const wrapper = this.wrappers.get(pageNumber);
        if (wrapper) delete wrapper.dataset.rendered;
        this.rendered.delete(pageNumber);
    }

    rerenderAll() {
        [...this.rendered.keys()].forEach((page) => this.release(page));
        this.update();
    }

    disconnect() {
        this.observer?.disconnect();
        this.resizeObserver?.disconnect();
        [...this.rendered.keys()].forEach((page) => this.release(page));
        this.wrappers.clear();
        this.visible.clear();
    }

    destroy() {
        this.disconnect();
        this.pdf?.destroy();
    }
}

/**
 * First page as a small JPEG data URL, for the dashboard card preview.
 */
export async function renderThumbnail(pdf, maxWidth = 360) {
    const page = await pdf.getPage(1);
    const base = page.getViewport({ scale: 1 });
    const viewport = page.getViewport({ scale: Math.min(maxWidth / base.width, 2) });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    await page.render({ canvasContext: context, viewport }).promise;
    const dataUrl = canvas.toDataURL('image/jpeg', 0.78);
    canvas.width = 0;
    return dataUrl;
}
