import { openPdf, VirtualPages } from './pdf-viewer';
import { createPad, INK_COLORS, padToPng, photoToPng, resizePad, SIGNATURE_FONTS, typedToPng } from './signature-capture';

const ACTIONABLE = ['SIGNATURE', 'INITIAL', 'TEXT', 'CHECKBOX'];
const POLL_MS = 30000;

function readStorage(key, fallback) {
    try {
        const raw = sessionStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function writeStorage(key, value) {
    try {
        sessionStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Quota exceeded or storage disabled: the draft just will not survive a reload.
    }
}

export default function signingPage(config) {
    const storageKey = `paraf:${config.token.slice(0, 16)}`;
    // PDF.js objects use private class fields, which break inside Alpine's reactive proxy.
    let viewer = null;
    let pad = null;

    return {
        config,
        pages: config.pages,
        fields: config.fields,
        values: readStorage(`${storageKey}:values`, {}),
        cache: readStorage(`${storageKey}:cache`, {}),
        errors: {},
        loading: true,
        loadError: null,
        highlightId: null,

        capture: { open: false, fieldId: null, kind: 'SIGNATURE', mode: 'draw', ink: 'black', text: '', font: SIGNATURE_FONTS[0], uploadPreview: null, busy: false, error: null },
        fonts: SIGNATURE_FONTS,
        inks: INK_COLORS,

        consent: false,
        submitting: false,
        offline: !navigator.onLine,
        pendingSubmit: false,
        submitError: null,

        declineReason: '',
        declinePreset: '',
        declining: false,
        declineError: null,

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
                this.loadError = 'Dokumen gagal dimuat. Periksa koneksi lalu muat ulang halaman.';
            }

            window.addEventListener('online', () => {
                this.offline = false;
                if (this.pendingSubmit) this.submit();
            });
            window.addEventListener('offline', () => { this.offline = true; });

            if (!this.cache['SIGNATURE'] && config.savedSignature) {
                this.cache['SIGNATURE'] = config.savedSignature;
            }
            if (!this.cache['INITIAL'] && config.savedInitial) {
                this.cache['INITIAL'] = config.savedInitial;
            }

            this.pollTimer = setInterval(() => this.poll(), POLL_MS);
        },

        destroy() {
            clearInterval(this.pollTimer);
            viewer?.destroy();
        },

        get myFields() {
            return this.fields
                .filter((field) => field.mine)
                .sort((a, b) => a.page - b.page || a.y - b.y || a.x - b.x);
        },

        get requiredFields() {
            return this.myFields.filter((field) => ACTIONABLE.includes(field.type) && (field.required || ['SIGNATURE', 'INITIAL'].includes(field.type)));
        },

        get filledCount() {
            return this.requiredFields.filter((field) => this.isFilled(field)).length;
        },

        get complete() {
            return this.filledCount === this.requiredFields.length;
        },

        isFilled(field) {
            const value = this.values[field.id];
            if (field.type === 'CHECKBOX') return value === true;
            return typeof value === 'string' && value.trim() !== '';
        },

        fieldsOn(pageNumber) {
            return this.fields.filter((field) => field.page === pageNumber);
        },

        fieldStyle(field) {
            const color = field.color ?? '#64748b';
            return {
                left: `${field.x * 100}%`,
                top: `${field.y * 100}%`,
                width: `${field.w * 100}%`,
                height: `${field.h * 100}%`,
                '--field-color': color,
            };
        },

        pageStyle(page) {
            return { aspectRatio: `${page.width} / ${page.height}` };
        },

        textSize(field, pageEl, text = '') {
            const height = (pageEl?.clientHeight ?? 1000) * field.h;
            const width = (pageEl?.clientWidth ?? 800) * field.w;
            const fitWidth = text ? (width - 8) / (text.length * 0.56) : Infinity;
            return `${Math.max(8, Math.min(height * 0.6, 16, fitWidth))}px`;
        },

        persist() {
            writeStorage(`${storageKey}:values`, this.values);
        },

        nextField() {
            return this.requiredFields.find((field) => !this.isFilled(field)) ?? null;
        },

        goNext() {
            const field = this.nextField();
            if (!field) {
                this.$dispatch('open-modal', 'confirm-submit');
                return;
            }
            this.scrollTo(field);
        },

        scrollTo(field) {
            const el = this.$root.querySelector(`[data-field="${field.id}"]`);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.highlightId = field.id;
            setTimeout(() => { if (this.highlightId === field.id) this.highlightId = null; }, 1600);
            if (field.type === 'TEXT') setTimeout(() => el.querySelector('input')?.focus({ preventScroll: true }), 450);
        },

        activate(field) {
            if (!field.mine) return;

            if (field.type === 'CHECKBOX') {
                this.values[field.id] = !this.values[field.id];
                this.afterChange(field);
                return;
            }

            if (field.type === 'SIGNATURE' || field.type === 'INITIAL') {
                // One-tap apply: the signature made for the first box fills the next ones.
                if (!this.isFilled(field) && this.cache[field.type]) {
                    this.values[field.id] = this.cache[field.type];
                    this.afterChange(field);
                    return;
                }
                this.openCapture(field);
            }
        },

        afterChange(field) {
            delete this.errors[field.id];
            this.persist();
        },

        openCapture(field) {
            Object.assign(this.capture, {
                open: true,
                fieldId: field.id,
                kind: field.type,
                mode: this.capture.mode,
                text: field.type === 'INITIAL' ? this.initials() : config.signerName,
                uploadPreview: null,
                error: null,
            });
            this.$dispatch('open-modal', 'signature-capture');
            setTimeout(() => this.setMode(this.capture.mode), 60);
        },

        // The canvas lives inside <x-modal>'s own x-data scope, so $refs cannot reach it.
        padCanvas() {
            return this.$root.querySelector('[data-pad-canvas]');
        },

        initials() {
            return config.signerName.split(/\s+/).filter(Boolean).slice(0, 3).map((part) => part[0].toUpperCase()).join('');
        },

        setMode(mode) {
            this.capture.mode = mode;
            this.capture.error = null;
            if (mode === 'draw') {
                this.$nextTick(() => {
                    const canvas = this.padCanvas();
                    if (!canvas) return;
                    if (!pad || pad.canvas !== canvas) {
                        pad?.off();
                        pad = createPad(canvas, INK_COLORS[this.capture.ink]);
                    } else {
                        resizePad(canvas, pad);
                    }
                });
            }
        },

        setInk(ink) {
            this.capture.ink = ink;
            if (pad) pad.penColor = INK_COLORS[ink];
        },

        clearPad() {
            pad?.clear();
        },

        undoPad() {
            if (!pad) return;
            const data = pad.toData();
            data.pop();
            pad.fromData(data);
        },

        async onUpload(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                this.capture.error = 'Pilih file gambar (JPG/PNG).';
                return;
            }
            this.capture.busy = true;
            try {
                this.capture.uploadPreview = await photoToPng(file, INK_COLORS[this.capture.ink]);
                if (!this.capture.uploadPreview) this.capture.error = 'Tanda tangan tidak terdeteksi di foto. Coba foto dengan latar putih yang lebih terang.';
            } catch {
                this.capture.error = 'Gambar gagal diproses.';
            } finally {
                this.capture.busy = false;
            }
        },

        async applyCapture() {
            this.capture.busy = true;
            this.capture.error = null;
            let png = null;

            try {
                if (this.capture.mode === 'draw') png = padToPng(this.padCanvas(), pad);
                if (this.capture.mode === 'type') png = await typedToPng(this.capture.text, this.capture.font, INK_COLORS[this.capture.ink]);
                if (this.capture.mode === 'upload') png = this.capture.uploadPreview;
            } finally {
                this.capture.busy = false;
            }

            if (!png) {
                this.capture.error = this.capture.mode === 'draw' ? 'Buat coretan tanda tangan dulu.' : this.capture.mode === 'type' ? 'Ketik nama Anda dulu.' : 'Unggah foto tanda tangan dulu.';
                return;
            }

            const field = this.fields.find((item) => item.id === this.capture.fieldId);
            this.values[field.id] = png;
            this.cache[field.type] = png;
            writeStorage(`${storageKey}:cache`, this.cache);
            this.afterChange(field);
            pad?.clear();
            this.capture.open = false;
            this.$dispatch('close-modal', 'signature-capture');

            // Fill the remaining empty boxes of the same kind right away.
            this.myFields
                .filter((item) => item.type === field.type && !this.isFilled(item))
                .forEach((item) => { this.values[item.id] = png; });
            this.persist();
        },

        applySavedPreset() {
            const preset = this.capture.kind === 'INITIAL' ? this.config.savedInitial : this.config.savedSignature;
            if (!preset) return;

            const field = this.fields.find((item) => item.id === this.capture.fieldId);
            if (!field) return;

            this.values[field.id] = preset;
            this.cache[field.type] = preset;
            writeStorage(`${storageKey}:cache`, this.cache);
            this.afterChange(field);
            pad?.clear();
            this.capture.open = false;
            this.$dispatch('close-modal', 'signature-capture');

            // Auto-fill other boxes of same kind
            this.myFields
                .filter((item) => item.type === field.type && !this.isFilled(item))
                .forEach((item) => { this.values[item.id] = preset; });
            this.persist();
        },

        clearField(field) {
            delete this.values[field.id];
            this.persist();
        },

        payload() {
            const fields = {};
            this.myFields.forEach((field) => {
                if (ACTIONABLE.includes(field.type) && this.values[field.id] !== undefined) {
                    fields[field.id] = field.type === 'CHECKBOX' ? (this.values[field.id] ? '1' : '0') : this.values[field.id];
                }
            });
            return { fields, consent: this.consent };
        },

        async submit() {
            if (this.submitting) return;
            if (!this.consent) {
                this.submitError = 'Centang pernyataan persetujuan terlebih dahulu.';
                return;
            }

            this.submitting = true;
            this.submitError = null;

            try {
                const response = await fetch(config.submitUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(this.payload()),
                });

                const body = await response.json().catch(() => ({}));

                if (response.ok) {
                    this.pendingSubmit = false;
                    try {
                        sessionStorage.removeItem(`${storageKey}:values`);
                    } catch {}
                    window.location.href = body.redirect ?? config.returnUrl;
                    return;
                }

                if (response.status === 422) {
                    this.errors = {};
                    Object.entries(body.errors ?? {}).forEach(([key, messages]) => {
                        const id = key.startsWith('fields.') ? key.slice(7) : key;
                        this.errors[id] = messages[0];
                    });
                    this.submitError = body.errors?.document?.[0] ?? body.errors?.consent?.[0] ?? 'Masih ada isian yang perlu diperbaiki.';
                    const first = this.myFields.find((field) => this.errors[field.id]);
                    if (first) {
                        this.$dispatch('close-modal', 'confirm-submit');
                        this.scrollTo(first);
                    }
                    return;
                }

                this.submitError = body.message || 'Tanda tangan gagal dikirim. Coba lagi.';
            } catch (error) {
                // Network failure: keep everything in sessionStorage and resend once online.
                this.offline = true;
                this.pendingSubmit = true;
                this.submitError = null;
            } finally {
                this.submitting = false;
            }
        },

        async decline() {
            const reason = (this.declinePreset === 'Lainnya' || !this.declinePreset ? this.declineReason : `${this.declinePreset}${this.declineReason ? ': ' + this.declineReason : ''}`).trim();
            if (reason.length < 5) {
                this.declineError = 'Tuliskan alasan penolakan (minimal 5 karakter).';
                return;
            }

            this.declining = true;
            this.declineError = null;
            try {
                const response = await fetch(config.declineUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ reason }),
                });
                const body = await response.json().catch(() => ({}));
                if (response.ok) {
                    window.location.href = body.redirect ?? config.returnUrl;
                    return;
                }
                this.declineError = body.errors ? Object.values(body.errors)[0][0] : (body.message || 'Gagal mengirim penolakan.');
            } catch {
                this.declineError = 'Koneksi terputus. Coba lagi setelah internet tersambung.';
            } finally {
                this.declining = false;
            }
        },

        // Owner voided / someone declined / link expired while this tab was open.
        async poll() {
            if (this.submitting || document.hidden) return;
            try {
                const response = await fetch(config.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const status = await response.json();
                if (status.closed) window.location.reload();
            } catch {
                // Offline: the banner is driven by the online/offline events.
            }
        },
    };
}
