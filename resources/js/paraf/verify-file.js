/**
 * Hashes a PDF locally (Web Crypto) and compares it with the hashes on record.
 * The file never leaves the browser.
 */
export default function verifyFile({ completed, original }) {
    return {
        state: 'idle',
        hash: null,
        fileName: null,
        fileSize: null,
        isDragging: false,

        formatBytes(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        async check(event) {
            this.isDragging = false;
            const file = event.target.files?.[0] ?? event.dataTransfer?.files?.[0];
            if (!file) return;

            if (!window.crypto?.subtle) {
                this.state = 'unsupported';
                return;
            }

            this.state = 'hashing';
            this.fileName = file.name;
            this.fileSize = this.formatBytes(file.size);

            try {
                const buffer = await file.arrayBuffer();
                const digest = await crypto.subtle.digest('SHA-256', buffer);
                this.hash = [...new Uint8Array(digest)]
                    .map((byte) => byte.toString(16).padStart(2, '0'))
                    .join('');

                if (completed && this.hash.toLowerCase() === completed.toLowerCase()) {
                    this.state = 'match';
                } else if (original && this.hash.toLowerCase() === original.toLowerCase()) {
                    this.state = 'original';
                } else {
                    this.state = 'mismatch';
                }
            } catch (err) {
                console.error('Failed to hash file:', err);
                this.state = 'mismatch';
            }
        },

        reset() {
            this.state = 'idle';
            this.hash = null;
            this.fileName = null;
            this.fileSize = null;
            this.isDragging = false;
        },
    };
}

