import { notifier } from '../core/Notifier.js';

/**
 * Verwaltet die Gutschein-Ansicht im Admin-Bereich.
 * Übernimmt das QR-Code-Modal und das Kopieren von Links in die Zwischenablage.
 * Ersetzt die alten inline Funktionen in tab_vouchers.phtml.
 */
export class VoucherManager {
    constructor(container) {
        this.container = container;
        this.modal = document.getElementById('qrModal');
        this.img = document.getElementById('qrModalImg');
        this.loader = document.getElementById('qrModalLoader');
        this.codeDisplay = document.getElementById('qrModalCode');

        this.init();
    }

    init() {
        // Event-Delegation für die Tabelle (Buttons)
        this.container.addEventListener('click', (e) => {
            const qrBtn = e.target.closest('.js-show-qr');
            const copyBtn = e.target.closest('.js-copy-link');

            if (qrBtn) {
                e.preventDefault();
                this.showQr(qrBtn.dataset.code, qrBtn.dataset.url);
            }

            if (copyBtn) {
                e.preventDefault();
                this.copyLink(copyBtn.dataset.url, copyBtn);
            }
        });

        // Schließen-Events für das Modal
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) this.closeQr();
            });
            const closeBtn = this.modal.querySelector('.js-close-modal');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeQr());
            }
        }
    }

    showQr(code, url) {
        if (!this.modal || !this.img || !this.loader || !this.codeDisplay) return;

        this.codeDisplay.innerText = code;
        this.img.style.display = 'none';
        this.loader.style.display = 'block';
        this.modal.style.display = 'flex';

        const encodedUrl = encodeURIComponent(url);
        this.img.onload = () => {
            this.loader.style.display = 'none';
            this.img.style.display = 'block';
        };
        this.img.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=${encodedUrl}`;
    }

    closeQr() {
        if (!this.modal || !this.img) return;
        this.modal.style.display = 'none';
        this.img.src = '';
    }

    async copyLink(url, element) {
        const originalText = element.innerText;
        const showSuccess = () => {
            element.innerText = 'Kopiert! ✓';
            element.style.color = 'var(--success-color)';
            notifier.show('Gutschein-Link kopiert!');
            setTimeout(() => {
                element.innerText = originalText;
                element.style.color = 'var(--primary-color)';
            }, 2000);
        };

        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(url);
                showSuccess();
            } catch (err) {
                this.fallbackCopyText(url, showSuccess);
            }
        } else {
            this.fallbackCopyText(url, showSuccess);
        }
    }

    fallbackCopyText(text, callback) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            if (document.execCommand('copy')) callback();
        } catch (err) {
            console.error('Fallback Kopieren fehlgeschlagen', err);
        }
        document.body.removeChild(textArea);
    }
}
