import { openPdf, renderThumbnail } from './pdf-viewer';

/**
 * Checks the chosen PDF in the browser before the Livewire upload finishes: password
 * protection, page count, and a first-page thumbnail the server stores for the card.
 */
export default function pdfUploadPreview({ maxBytes }) {
    return {
        checking: false,
        error: null,
        pageCount: null,
        thumbnail: null,

        async inspect(event) {
            const file = event.target.files?.[0];
            this.error = null;
            this.pageCount = null;
            this.thumbnail = null;
            this.$wire.set('thumbnail', null, false);

            if (!file) return;

            if (file.type && file.type !== 'application/pdf') {
                this.error = 'File harus berupa PDF.';
                return;
            }
            if (file.size > maxBytes) {
                this.error = `Ukuran PDF maksimal ${Math.round(maxBytes / 1024 / 1024)} MB.`;
                return;
            }

            if (!this.$wire.title) {
                this.$wire.set('title', file.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').trim().slice(0, 150), false);
            }

            this.checking = true;
            let pdf = null;
            try {
                pdf = await openPdf(await file.arrayBuffer());
                this.pageCount = pdf.numPages;
                this.thumbnail = await renderThumbnail(pdf);
                this.$wire.set('thumbnail', this.thumbnail, false);
            } catch (error) {
                this.error = error?.name === 'PasswordException'
                    ? 'PDF terproteksi password. Silakan unggah file tanpa password.'
                    : 'File PDF rusak atau tidak bisa dibaca.';
            } finally {
                pdf?.destroy();
                this.checking = false;
            }
        },
    };
}
