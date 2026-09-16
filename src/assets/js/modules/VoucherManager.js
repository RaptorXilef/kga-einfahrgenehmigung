import { notifier } from '../core/Notifier.js';

/**
 * Modulares Management für die Gutschein-Ansicht im Admin-Dashboard.
 * Übernimmt die Erstellung von QR-Codes via API und das plattformübergreifende Kopieren von Links.
 */
export class VoucherManager {
    constructor(container) {
        this.container = container;
        this.qrButtons = this.container.querySelectorAll('.js-show-qr');
        this.copyButtons = this.container.querySelectorAll('.js-copy-link');

        // Modal-Elemente auflösen
        this.modal = document.querySelector('.js-qr-modal');
        this.modalLoader = document.querySelector('.js-qr-modal-loader');
        this.modalCode = document.querySelector('.js-qr-modal-code');
        this.qrBox = document.querySelector('.js-qr-box');
        this.closeBtn = this.modal?.querySelector('.js-close-modal');

        this.abortController = new AbortController();

        this.init();
    }

    init() {
        const options = { signal: this.abortController.signal };

        this.qrButtons.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.showQr(btn.dataset.code, btn.dataset.url);
                },
                options
            );
        });

        this.copyButtons.forEach((btn) => {
            btn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.copyLink(btn.dataset.url, btn);
                },
                options
            );
        });

        if (this.closeBtn) {
            this.closeBtn.addEventListener(
                'click',
                (e) => {
                    e.preventDefault();
                    this.closeQr();
                },
                options
            );
        }

        if (this.modal) {
            this.modal.addEventListener(
                'click',
                (e) => {
                    if (e.target === this.modal) this.closeQr();
                },
                options
            );
        }
    }

    showQr(code, url) {
        if (!this.modal || !this.qrBox) return;

        this.modalCode.innerText = code;

        // Modal resetten
        this.modalLoader.hidden = false;
        this.modalLoader.classList.remove('is-error');
        this.modalLoader.innerText = 'Wird generiert...';

        // Altes Bild restlos aus dem DOM löschen (verhindert Phantom-Requests)
        const oldImg = this.qrBox.querySelector('img');
        if (oldImg) oldImg.remove();

        this.modal.showModal();

        // Die QR-Code API url-encoded aufrufen
        const encodedUrl = encodeURIComponent(url);
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=${encodedUrl}`;
        // const baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        // const qrUrl = `${baseUrl}api/generate_qr?data=${encodedUrl}`;

        // Bild dynamisch (frisch) erzeugen für sauberes, konfliktfreies Rendering
        const img = document.createElement('img');
        img.className = 'c-voucher-qr-img js-qr-modal-img';
        img.alt = 'QR Code';
        img.hidden = true; // Versteckt lassen, bis es vollständig geladen ist

        // Wir blenden das Bild erst ein, wenn die externe API es fertig gerendert hat
        img.onload = () => {
            this.modalLoader.hidden = true;
            img.hidden = false;
        };

        // Fehlerbehandlung, falls die externe API offline oder geblockt ist!
        img.onerror = () => {
            img.hidden = true;
            this.modalLoader.hidden = false;
            this.modalLoader.innerText = 'Fehler: QR-Code konnte nicht generiert werden.';
            this.modalLoader.classList.add('is-error');
        };

        // Src zuweisen und in den DOM einhängen (Löst exakt EINEN Request aus)
        img.src = qrUrl;
        this.qrBox.appendChild(img);
    }

    closeQr() {
        if (!this.modal) return;
        this.modal.close();

        // Nach dem Schließen sofort aufräumen
        const oldImg = this.qrBox.querySelector('img');
        if (oldImg) oldImg.remove();

        this.modalLoader.classList.remove('is-error');
    }

    async copyLink(url, element) {
        // Lock-State verhindert permanente Zerstörung des Button-Texts durch Spam-Klicks
        if (element.dataset.isCopying) return;
        element.dataset.isCopying = 'true';

        const originalHtml = element.innerHTML;

        const successAction = () => {
            element.innerText = 'Kopiert! ✓';
            element.classList.add('is-success');
            notifier.show('Gutschein-Link in die Zwischenablage kopiert!', 'success');

            // Reset nach 2 Sekunden
            setTimeout(() => {
                element.innerHTML = originalHtml;
                element.classList.remove('is-success');
                delete element.dataset.isCopying; // Lock wieder freigeben
            }, 2000);
        };

        // 1. Moderne Clipboard API bevorzugen
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(url);
                successAction();
            } catch (err) {
                console.error('[VoucherManager] API-Clipboard fehlgeschlagen:', err);
                this.fallbackCopyText(url, element, successAction);
            }
        } else {
            // 2. Legacy Fallback (z.B. für ungesicherte lokale Umgebungen)
            this.fallbackCopyText(url, element, successAction);
        }
    }

    // Element Parameter in der Methodensignatur ergänzt
    fallbackCopyText(text, element, callback) {
        const textArea = document.createElement('textarea');
        textArea.value = text;

        // Außerhalb des sichtbaren Bereichs positionieren
        textArea.className = 'u-visually-hidden';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                callback();
            } else {
                // Silent-Failures abfangen und Lock freigeben
                notifier.show('Fehler: Browser blockiert die Zwischenablage.', 'error');
                if (element) delete element.dataset.isCopying;
            }
        } catch (err) {
            console.error('[VoucherManager] Fallback-Kopieren fehlgeschlagen', err);
            notifier.show('Fehler beim Kopieren des Links.', 'error');
            // Bei Fehler auch das Lock freigeben
            if (element) delete element.dataset.isCopying;
        }

        document.body.removeChild(textArea);
    }

    destroy() {
        this.abortController.abort();
    }
}
