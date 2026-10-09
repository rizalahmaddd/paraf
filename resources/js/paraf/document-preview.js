import { openPdf, VirtualPages } from './pdf-viewer';

const FIELD_LABELS = {
    SIGNATURE: 'Tanda Tangan',
    INITIAL: 'Paraf',
    TEXT: 'Isian Teks',
    DATE: 'Tanggal',
    CHECKBOX: 'Centang',
};

export default function documentPreview(config) {
    let viewer = null;
    let pageObserver = null;
    let fullscreenHandler = null;

    return {
        config,
        pages: config.pages || [],
        fields: config.fields || [],
        signers: config.signers || [],
        variant: config.hasCompleted ? 'final' : 'original',
        currentPdfUrl: config.pdfUrl,
        loading: true,
        loadError: null,
        currentPage: 1,
        zoomLevel: 100,
        showFields: !config.hasCompleted,
        isFullscreen: false,

        async init() {
            await this.loadDocument(this.currentPdfUrl);

            fullscreenHandler = () => {
                this.isFullscreen = Boolean(document.fullscreenElement);
                this.$nextTick(() => viewer?.rerenderAll());
            };
            document.addEventListener('fullscreenchange', fullscreenHandler);

            this.setupScrollObserver();
        },

        async loadDocument(url) {
            this.loading = true;
            this.loadError = null;

            if (viewer) {
                viewer.destroy();
                viewer = null;
            }

            try {
                const pdf = await openPdf(url);
                this.loading = false;
                await this.$nextTick();

                const container = this.$refs.canvasContainer;
                viewer = new VirtualPages(pdf, { buffer: 1, root: container || null });
                const pageWrappers = [...(container || this.$root).querySelectorAll('[data-page]')];
                if (pageWrappers.length > 0) {
                    viewer.observe(pageWrappers);
                }
            } catch (error) {
                console.error('Error loading PDF preview:', error);
                this.loading = false;
                this.loadError = 'Dokumen gagal dimuat. Periksa koneksi atau klik untuk memuat ulang.';
            }
        },

        async switchVariant(v) {
            if (this.variant === v && !this.loadError) return;
            this.variant = v;
            const targetUrl = v === 'original'
                ? this.config.originalPdfUrl
                : (this.config.finalPdfUrl || this.config.pdfUrl);
            this.currentPdfUrl = targetUrl;
            await this.loadDocument(targetUrl);
        },

        async reload() {
            await this.loadDocument(this.currentPdfUrl);
        },

        setupScrollObserver() {
            const container = this.$refs.canvasContainer;
            if (!container) return;

            pageObserver?.disconnect();
            pageObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const pageNum = Number(entry.target.dataset.page);
                        if (pageNum) {
                            this.currentPage = pageNum;
                        }
                    }
                });
            }, {
                root: container,
                threshold: 0.35,
            });

            this.$nextTick(() => {
                container.querySelectorAll('[data-page]').forEach((el) => pageObserver.observe(el));
            });
        },

        goToPage(pageNumber) {
            if (pageNumber < 1 || pageNumber > this.pages.length) return;
            this.currentPage = pageNumber;
            const container = this.$refs.canvasContainer;
            const target = container?.querySelector(`[data-page="${pageNumber}"]`);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.goToPage(this.currentPage - 1);
            }
        },

        nextPage() {
            if (this.currentPage < this.pages.length) {
                this.goToPage(this.currentPage + 1);
            }
        },

        zoomIn() {
            if (this.zoomLevel < 160) {
                this.zoomLevel += 20;
                this.$nextTick(() => viewer?.rerenderAll());
            }
        },

        zoomOut() {
            if (this.zoomLevel > 60) {
                this.zoomLevel -= 20;
                this.$nextTick(() => viewer?.rerenderAll());
            }
        },

        resetZoom() {
            this.zoomLevel = 100;
            this.$nextTick(() => viewer?.rerenderAll());
        },

        toggleFullscreen() {
            const wrapper = this.$refs.viewerWrapper;
            if (!wrapper) return;

            if (!document.fullscreenElement) {
                wrapper.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        },

        signer(id) {
            return this.signers.find((s) => s.id === id) ?? { name: 'Penandatangan', color: '#64748b' };
        },

        fieldsOn(pageNumber) {
            return this.fields.filter((f) => f.page === pageNumber);
        },

        fieldLabel(type) {
            return FIELD_LABELS[type] || type;
        },

        pageStyle(page) {
            return {
                aspectRatio: `${page.width} / ${page.height}`,
                width: `${this.zoomLevel}%`,
                maxWidth: this.zoomLevel === 100 ? '820px' : 'none',
            };
        },

        fieldStyle(field) {
            const color = this.signer(field.signer_id).color;
            return {
                left: `${field.x * 100}%`,
                top: `${field.y * 100}%`,
                width: `${field.w * 100}%`,
                height: `${field.h * 100}%`,
                borderColor: color,
                backgroundColor: `${color}18`,
                color,
            };
        },

        destroy() {
            pageObserver?.disconnect();
            if (fullscreenHandler) {
                document.removeEventListener('fullscreenchange', fullscreenHandler);
            }
            viewer?.destroy();
        },
    };
}
