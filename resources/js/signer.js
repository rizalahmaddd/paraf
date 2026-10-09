import './icons';
import './theme';
import signingPage from './paraf/signing-page';
import verifyFile from './paraf/verify-file';

document.addEventListener('alpine:init', () => {
    window.Alpine.store('signing', { filled: 0, total: 0 });
    window.Alpine.data('signingPage', signingPage);
    window.Alpine.data('verifyFile', verifyFile);
});
