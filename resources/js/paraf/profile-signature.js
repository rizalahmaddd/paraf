const MODAL = 'capture-profile-specimen';
const FONTS = ['Dancing Script', 'Caveat', 'Sacramento', 'Great Vibes'];

let capture = null;
const loadCapture = () => (capture ??= import('./signature-capture'));

export default function profileSignature({ name }) {
    let pad = null;

    return {
        kind: 'SIGNATURE',
        mode: 'draw',
        typedText: '',
        selectedFont: FONTS[0],
        fonts: FONTS,
        uploadPreview: null,
        error: null,
        saving: false,

        initials() {
            return name.trim().split(/\s+/).filter(Boolean).slice(0, 2).map((word) => word[0].toUpperCase()).join('');
        },

        open(kind) {
            this.kind = kind;
            this.mode = 'draw';
            this.error = null;
            this.uploadPreview = null;
            this.typedText = kind === 'INITIAL' ? this.initials() : name;
            this.$dispatch('open-modal', MODAL);
            this.$nextTick(() => setTimeout(() => this.preparePad(), 50));
        },

        setMode(mode) {
            this.mode = mode;
            this.error = null;
            if (mode === 'draw') this.$nextTick(() => this.preparePad());
        },

        async preparePad() {
            const { createPad, INK_COLORS, resizePad } = await loadCapture();
            const canvas = this.$refs.padCanvas;
            if (!canvas || !canvas.offsetWidth) return;
            if (pad) {
                resizePad(canvas, pad);
                pad.clear();
            } else {
                pad = createPad(canvas, INK_COLORS.black);
            }
        },

        clearPad() {
            pad?.clear();
        },

        undoPad() {
            if (!pad) return;
            const data = pad.toData();
            if (data.length) {
                data.pop();
                pad.fromData(data);
            }
        },

        async onUpload(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;

            try {
                const { INK_COLORS, photoToPng } = await loadCapture();
                this.uploadPreview = await photoToPng(file, INK_COLORS.black);
                this.error = this.uploadPreview ? null : 'Tidak ada goresan yang terbaca dari foto ini.';
            } catch {
                this.error = 'Gambar tidak bisa dibaca. Coba foto lain (PNG/JPG).';
            }
        },

        async capture() {
            const { INK_COLORS, padToPng, typedToPng } = await loadCapture();
            if (this.mode === 'draw') {
                return pad ? padToPng(this.$refs.padCanvas, pad) : null;
            }
            if (this.mode === 'type') {
                return typedToPng(this.typedText, this.selectedFont, INK_COLORS.black);
            }
            return this.uploadPreview;
        },

        async save() {
            const dataUrl = await this.capture();
            if (!dataUrl) {
                this.error = {
                    draw: 'Gambar tanda tangan terlebih dahulu.',
                    type: 'Ketik nama atau inisial terlebih dahulu.',
                    upload: 'Pilih foto tanda tangan terlebih dahulu.',
                }[this.mode];
                return;
            }

            this.saving = true;
            this.error = null;
            try {
                const ok = await this.$wire.saveSpecimen(this.kind, dataUrl);
                if (ok) {
                    this.$dispatch('close-modal', MODAL);
                } else {
                    this.error = 'Gambar tidak valid. Coba buat ulang.';
                }
            } catch {
                this.error = 'Gagal menyimpan. Periksa koneksi lalu coba lagi.';
            } finally {
                this.saving = false;
            }
        },
    };
}
