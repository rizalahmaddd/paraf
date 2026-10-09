//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import './icons';
import './theme';
import './responsive-tables';
import './searchable-select';
import './stale-assets';
import './page-skeleton';
import documentEditor from './paraf/document-editor';
import documentPreview from './paraf/document-preview';
import pdfUploadPreview from './paraf/upload-preview';
import profileSignature from './paraf/profile-signature';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('profileSignature', profileSignature);
    window.Alpine.data('documentEditor', documentEditor);
    window.Alpine.data('documentPreview', documentPreview);
    window.Alpine.data('pdfUploadPreview', pdfUploadPreview);
});

window.parafCopy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
};
