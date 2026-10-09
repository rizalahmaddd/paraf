import { openPdf, VirtualPages } from './pdf-viewer';

// Default size in px for a page rendered 800px wide; converted to ratios on drop.
const FIELD_TYPES = {
    SIGNATURE: { label: 'Tanda tangan', icon: 'signature', width: 180, height: 64, minWidth: 120, minHeight: 40 },
    INITIAL: { label: 'Paraf', icon: 'pen-line', width: 90, height: 48, minWidth: 48, minHeight: 32 },
    DATE: { label: 'Tanggal', icon: 'calendar', width: 150, height: 30, minWidth: 80, minHeight: 20 },
    NAME: { label: 'Nama signer', icon: 'user-round', width: 190, height: 30, minWidth: 80, minHeight: 20 },
    TEXT: { label: 'Teks', icon: 'type', width: 190, height: 30, minWidth: 60, minHeight: 20 },
    CHECKBOX: { label: 'Checkbox', icon: 'square-check', width: 26, height: 26, minWidth: 16, minHeight: 16 },
};

const REFERENCE_WIDTH = 800;
const SNAP_PX = 6;

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
const round4 = (value) => Math.round(value * 10000) / 10000;

export default function documentEditor(config) {
    let viewer = null;
    let pageObserver = null;
    let fullscreenHandler = null;

    return {
        pages: config.pages,
        signers: config.signers,
        fields: config.fields.map((field) => ({ ...field })),
        types: FIELD_TYPES,
        activeSignerId: config.signers[0]?.id ?? null,
        selectedId: null,
        selectedIds: [],
        placingType: null,
        guides: [],
        loading: true,
        loadError: null,
        saving: false,
        dirty: false,
        lastSavedAt: null,
        saveError: null,
        saveTimer: null,
        drag: null,
        currentPage: 1,
        zoomLevel: 100,
        snappingEnabled: true,
        isFullscreen: false,

        async init() {
            try {
                const pdf = await openPdf(config.pdfUrl);
                this.loading = false;
                await this.$nextTick();
                viewer = new VirtualPages(pdf, { buffer: 1 });
                viewer.observe([...this.$root.querySelectorAll('[data-page]')]);
            } catch (error) {
                console.error(error);
                this.loading = false;
                this.loadError = 'PDF gagal dimuat. Muat ulang halaman.';
            }

            this._onMove = (event) => this.onPointerMove(event);
            this._onUp = () => this.onPointerUp();
            window.addEventListener('pointermove', this._onMove);
            window.addEventListener('pointerup', this._onUp);

            fullscreenHandler = () => {
                this.isFullscreen = Boolean(document.fullscreenElement);
                this.$nextTick(() => viewer?.rerenderAll());
            };
            document.addEventListener('fullscreenchange', fullscreenHandler);

            window.addEventListener('beforeunload', this._onUnload = (event) => {
                if (this.dirty) event.preventDefault();
            });

            this.setupScrollObserver();
        },

        setupScrollObserver() {
            pageObserver?.disconnect();
            pageObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const pageNum = Number(entry.target.dataset.page);
                        if (pageNum) this.currentPage = pageNum;
                    }
                });
            }, {
                threshold: 0.35,
            });

            this.$nextTick(() => {
                this.$root.querySelectorAll('[data-page]').forEach((el) => pageObserver.observe(el));
            });
        },

        goToPage(pageNumber) {
            if (pageNumber < 1 || pageNumber > this.pages.length) return;
            this.currentPage = pageNumber;
            const target = this.$root.querySelector(`[data-page="${pageNumber}"]`);
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
            if (!document.fullscreenElement) {
                this.$root.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        },

        toggleSnapping() {
            this.snappingEnabled = !this.snappingEnabled;
        },

        applyInitialToAllPages() {
            if (!this.activeSignerId) return;

            let addedCount = 0;
            this.pages.forEach((page) => {
                const hasInitial = this.fields.some(
                    (f) => f.page === page.number && f.signer_id === this.activeSignerId && f.type === 'INITIAL'
                );

                if (!hasInitial) {
                    const widthRatio = round4(90 / (page.width || REFERENCE_WIDTH));
                    const heightRatio = round4(48 / (page.height || (REFERENCE_WIDTH * 1.414)));
                    const x = round4(clamp(0.82, 0, 1 - widthRatio));
                    const y = round4(clamp(0.91, 0, 1 - heightRatio));

                    this.fields.push({
                        id: crypto.randomUUID(),
                        signer_id: this.activeSignerId,
                        page: page.number,
                        x,
                        y,
                        w: widthRatio,
                        h: heightRatio,
                        type: 'INITIAL',
                        label: 'Paraf',
                        required: true,
                    });
                    addedCount++;
                }
            });

            if (addedCount > 0) {
                this.changed();
            }
        },

        clearPage(pageNumber) {
            const initialLen = this.fields.length;
            this.fields = this.fields.filter((f) => f.page !== pageNumber);
            if (this.selected && this.selected.page === pageNumber) {
                this.selectedId = null;
            }
            if (this.fields.length !== initialLen) {
                this.changed();
            }
        },

        destroy() {
            pageObserver?.disconnect();
            if (fullscreenHandler) {
                document.removeEventListener('fullscreenchange', fullscreenHandler);
            }
            viewer?.destroy();
            window.removeEventListener('pointermove', this._onMove);
            window.removeEventListener('pointerup', this._onUp);
            window.removeEventListener('beforeunload', this._onUnload);
        },

        signer(id) {
            return this.signers.find((signer) => signer.id === id) ?? { name: '?', color: '#64748b' };
        },

        get selected() {
            return this.fields.find((field) => field.id === this.selectedId) ?? null;
        },

        get selectedFields() {
            return this.fields.filter((field) => this.selectedIds.includes(field.id));
        },

        isSelected(id) {
            return this.selectedIds.includes(id) || this.selectedId === id;
        },

        selectField(field, event) {
            if (event?.shiftKey || event?.ctrlKey || event?.metaKey) {
                if (this.selectedIds.includes(field.id)) {
                    this.selectedIds = this.selectedIds.filter((id) => id !== field.id);
                    this.selectedId = this.selectedIds[0] ?? null;
                } else {
                    this.selectedIds.push(field.id);
                    this.selectedId = field.id;
                }
            } else {
                this.selectedIds = [field.id];
                this.selectedId = field.id;
            }
            this.activeSignerId = field.signer_id;
        },

        deselectAll() {
            this.selectedIds = [];
            this.selectedId = null;
        },

        fieldsOn(pageNumber) {
            return this.fields.filter((field) => field.page === pageNumber);
        },

        countFor(signerId, types = null) {
            return this.fields.filter((field) => field.signer_id === signerId && (!types || types.includes(field.type))).length;
        },

        signersMissingSignature() {
            return this.signers.filter((signer) => this.countFor(signer.id, ['SIGNATURE', 'INITIAL']) === 0);
        },

        fieldStyle(field) {
            const color = this.signer(field.signer_id).color;
            return {
                left: `${field.x * 100}%`,
                top: `${field.y * 100}%`,
                width: `${field.w * 100}%`,
                height: `${field.h * 100}%`,
                borderColor: color,
                backgroundColor: `${color}1f`,
                color,
            };
        },

        pageStyle(page) {
            return {
                aspectRatio: `${page.width} / ${page.height}`,
                width: `${this.zoomLevel}%`,
                maxWidth: this.zoomLevel === 100 ? '900px' : 'none',
            };
        },

        startPlacing(type) {
            if (!this.activeSignerId) return;
            this.placingType = this.placingType === type ? null : type;
            this.selectedId = null;
        },

        onPaletteDragStart(event, type) {
            event.dataTransfer.setData('text/paraf-field', type);
            event.dataTransfer.effectAllowed = 'copy';
        },

        onPageDrop(event, page) {
            const type = event.dataTransfer.getData('text/paraf-field');
            if (FIELD_TYPES[type]) this.addField(type, page, event);
        },

        onPageClick(event, page) {
            if (!this.placingType) return;
            this.addField(this.placingType, page, event);
            this.placingType = null;
        },

        addField(type, page, event) {
            const pageEl = event.currentTarget.closest('[data-page]');
            const rect = pageEl.getBoundingClientRect();
            const spec = FIELD_TYPES[type];

            const w = spec.width / rect.width;
            const h = spec.height / rect.height;
            const x = clamp((event.clientX - rect.left) / rect.width - w / 2, 0, 1 - w);
            const y = clamp((event.clientY - rect.top) / rect.height - h / 2, 0, 1 - h);

            const field = {
                id: crypto.randomUUID(),
                signer_id: this.activeSignerId,
                page: page.number,
                x: round4(x),
                y: round4(y),
                w: round4(w),
                h: round4(h),
                type,
                label: type === 'TEXT' ? 'Isian teks' : type === 'CHECKBOX' ? 'Saya setuju' : null,
                required: type !== 'CHECKBOX',
            };

            this.fields.push(field);
            this.selectedId = field.id;
            this.changed();
        },

        startDrag(event, field, mode) {
            if (event.button !== undefined && event.button !== 0) return;
            event.preventDefault();
            event.stopPropagation();

            if (event.shiftKey) {
                this.selectField(field, event);
                return;
            }

            if (!this.isSelected(field.id)) {
                this.selectedIds = [field.id];
                this.selectedId = field.id;
            }

            const pageEl = event.currentTarget.closest('[data-page]');
            const rect = pageEl.getBoundingClientRect();

            this.activeSignerId = field.signer_id;
            const origins = this.selectedFields.map((f) => ({ id: f.id, x: f.x, y: f.y, w: f.w, h: f.h, page: f.page }));
            this.drag = { field, mode, rect, startX: event.clientX, startY: event.clientY, origins, origin: { ...field }, moved: false };
        },

        onPointerMove(event) {
            if (!this.drag) return;

            const { field, mode, rect, startX, startY, origin, origins } = this.drag;
            const dx = (event.clientX - startX) / rect.width;
            const dy = (event.clientY - startY) / rect.height;

            if (Math.abs(event.clientX - startX) + Math.abs(event.clientY - startY) > 2) this.drag.moved = true;

            if (mode === 'move') {
                let x = clamp(origin.x + dx, 0, 1 - origin.w);
                let y = clamp(origin.y + dy, 0, 1 - origin.h);
                if (this.snappingEnabled) {
                    ({ x, y } = this.snap(field, x, y, rect));
                }
                const finalDx = x - origin.x;
                const finalDy = y - origin.y;

                if (origins && origins.length > 1) {
                    origins.forEach((o) => {
                        const target = this.fields.find((f) => f.id === o.id);
                        if (target && target.page === field.page) {
                            target.x = round4(clamp(o.x + finalDx, 0, 1 - o.w));
                            target.y = round4(clamp(o.y + finalDy, 0, 1 - o.h));
                        }
                    });
                } else {
                    field.x = round4(x);
                    field.y = round4(y);
                }
            } else {
                const spec = FIELD_TYPES[field.type];
                const minW = spec.minWidth / rect.width;
                const minH = spec.minHeight / rect.height;
                field.w = round4(clamp(origin.w + dx, minW, 1 - origin.x));
                field.h = round4(clamp(origin.h + dy, minH, 1 - origin.y));
            }
        },

        onPointerUp() {
            if (!this.drag) return;
            if (this.drag.moved) this.changed();
            this.drag = null;
            this.guides = [];
        },

        // Magnetic guides against other fields on the same page and the page centre line.
        snap(field, x, y, rect) {
            const others = this.fields.filter((other) => other.page === field.page && other.id !== field.id);
            const xTargets = [0.5];
            const yTargets = [];
            others.forEach((other) => {
                xTargets.push(other.x, other.x + other.w / 2, other.x + other.w);
                yTargets.push(other.y, other.y + other.h / 2, other.y + other.h);
            });

            const guides = [];
            const tolX = SNAP_PX / rect.width;
            const tolY = SNAP_PX / rect.height;

            const xEdges = [[0, x], [field.w / 2, x + field.w / 2], [field.w, x + field.w]];
            outerX: for (const [offset, edge] of xEdges) {
                for (const target of xTargets) {
                    if (Math.abs(edge - target) < tolX) {
                        x = target - offset;
                        guides.push({ axis: 'x', pos: target });
                        break outerX;
                    }
                }
            }

            const yEdges = [[0, y], [field.h / 2, y + field.h / 2], [field.h, y + field.h]];
            outerY: for (const [offset, edge] of yEdges) {
                for (const target of yTargets) {
                    if (Math.abs(edge - target) < tolY) {
                        y = target - offset;
                        guides.push({ axis: 'y', pos: target });
                        break outerY;
                    }
                }
            }

            this.guides = guides.map((guide) => ({ ...guide, page: field.page }));

            return { x: clamp(x, 0, 1 - field.w), y: clamp(y, 0, 1 - field.h) };
        },

        guideStyle(guide) {
            return guide.axis === 'x'
                ? { left: `${guide.pos * 100}%`, top: 0, bottom: 0, width: '1px' }
                : { top: `${guide.pos * 100}%`, left: 0, right: 0, height: '1px' };
        },

        onKeydown(event) {
            if (event.key === 'Escape' && this.placingType) {
                this.placingType = null;
                return;
            }

            const field = this.selected;
            if (!field || ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;

            // Shortcut Ctrl+D or Cmd+D: Duplicate
            if ((event.ctrlKey || event.metaKey) && (event.key === 'd' || event.key === 'D')) {
                event.preventDefault();
                this.duplicateSelected();
                return;
            }

            if (event.key === 'Delete' || event.key === 'Backspace') {
                event.preventDefault();
                this.removeSelected();
                return;
            }

            const step = (event.shiftKey ? 10 : 1) / 800;
            const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
            if (moves[event.key]) {
                event.preventDefault();
                field.x = round4(clamp(field.x + moves[event.key][0], 0, 1 - field.w));
                field.y = round4(clamp(field.y + moves[event.key][1], 0, 1 - field.h));
                this.changed();
            }
            if (event.key === 'Escape') this.deselectAll();
        },

        removeSelected() {
            if (this.selectedIds.length === 0 && !this.selectedId) return;
            const toRemove = this.selectedIds.length > 0 ? this.selectedIds : [this.selectedId];
            this.fields = this.fields.filter((field) => !toRemove.includes(field.id));
            this.deselectAll();
            this.changed();
        },

        duplicateSelected() {
            const targets = this.selectedFields.length > 0 ? this.selectedFields : (this.selected ? [this.selected] : []);
            if (targets.length === 0) return;
            const newIds = [];
            targets.forEach((field) => {
                const copy = {
                    ...field,
                    id: crypto.randomUUID(),
                    x: round4(clamp(field.x + 0.02, 0, 1 - field.w)),
                    y: round4(clamp(field.y + 0.02, 0, 1 - field.h)),
                };
                this.fields.push(copy);
                newIds.push(copy.id);
            });
            this.selectedIds = newIds;
            this.selectedId = newIds[0] ?? null;
            this.changed();
        },

        alignLeft() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const minX = Math.min(...targets.map((f) => f.x));
            targets.forEach((f) => { f.x = round4(minX); });
            this.changed();
        },

        alignRight() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const maxRight = Math.max(...targets.map((f) => f.x + f.w));
            targets.forEach((f) => { f.x = round4(clamp(maxRight - f.w, 0, 1 - f.w)); });
            this.changed();
        },

        alignTop() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const minY = Math.min(...targets.map((f) => f.y));
            targets.forEach((f) => { f.y = round4(minY); });
            this.changed();
        },

        alignBottom() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const maxBottom = Math.max(...targets.map((f) => f.y + f.h));
            targets.forEach((f) => { f.y = round4(clamp(maxBottom - f.h, 0, 1 - f.h)); });
            this.changed();
        },

        matchWidth() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const targetWidth = this.selected ? this.selected.w : targets[0].w;
            targets.forEach((f) => { f.w = round4(clamp(targetWidth, 0.02, 1 - f.x)); });
            this.changed();
        },

        matchHeight() {
            const targets = this.selectedFields;
            if (targets.length < 2) return;
            const targetHeight = this.selected ? this.selected.h : targets[0].h;
            targets.forEach((f) => { f.h = round4(clamp(targetHeight, 0.02, 1 - f.y)); });
            this.changed();
        },

        distributeVertical() {
            const targets = [...this.selectedFields].sort((a, b) => a.y - b.y);
            if (targets.length < 3) return;
            const first = targets[0];
            const last = targets[targets.length - 1];
            const totalDistance = last.y - first.y;
            const step = totalDistance / (targets.length - 1);
            targets.forEach((f, idx) => {
                f.y = round4(first.y + (step * idx));
            });
            this.changed();
        },

        assignSignerToSelected(signerId) {
            const targets = this.selectedFields;
            if (!targets.length) return;
            targets.forEach((f) => { f.signer_id = signerId; });
            this.activeSignerId = signerId;
            this.changed();
        },

        updateSelected(attributes) {
            if (!this.selected) return;
            Object.assign(this.selected, attributes);
            this.changed();
        },

        changed() {
            this.dirty = true;
            this.saveError = null;
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.save(), 1500);
        },

        async save() {
            clearTimeout(this.saveTimer);
            if (this.saving) {
                this.saveTimer = setTimeout(() => this.save(), 500);
                return false;
            }

            this.saving = true;
            const snapshot = JSON.stringify(this.fields);
            try {
                const result = await this.$wire.saveFields(JSON.parse(snapshot));
                if (result?.ok === false) {
                    this.saveError = result.message;
                    return false;
                }
                if (JSON.stringify(this.fields) === snapshot) this.dirty = false;
                this.lastSavedAt = new Date();
                return true;
            } catch (error) {
                this.saveError = 'Gagal menyimpan. Periksa koneksi lalu coba lagi.';
                return false;
            } finally {
                this.saving = false;
            }
        },

        async saveAndContinue() {
            if (await this.save()) {
                this.dirty = false;
                this.$wire.goToStep(3);
            }
        },

        savedLabel() {
            if (this.saving) return 'Menyimpan…';
            if (this.saveError) return this.saveError;
            if (this.dirty) return 'Perubahan belum disimpan';
            if (this.lastSavedAt) return `Tersimpan ${this.lastSavedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}`;
            return 'Semua perubahan tersimpan';
        },
    };
}
