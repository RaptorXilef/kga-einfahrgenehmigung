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
        this.modal = document.getElementById('qrModal');
        this.modalImg = document.getElementById('qrModalImg');
        this.modalLoader = document.getElementById('qrModalLoader');
        this.modalCode = document.getElementById('qrModalCode');
        this.closeBtn = this.modal?.querySelector('.js-close-modal');

        this.init();
    }

    init() {
        this.qrButtons.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.showQr(btn.dataset.code, btn.dataset.url);
            });
        });

        this.copyButtons.forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.copyLink(btn.dataset.url, btn);
            });
        });

        if (this.closeBtn) {
            this.closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.closeQr();
            });
        }

        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                // Nur schließen, wenn man auf den abgedunkelten Hintergrund klickt
                if (e.target === this.modal) this.closeQr();
            });
        }
    }

    showQr(code, url) {
        if (!this.modal) return;

        this.modalCode.innerText = code;

        // Sichtbarkeit an CSS Utilities delegieren
        this.modalImg.classList.add('u-hidden');
        this.modalLoader.classList.remove('u-hidden', 'is-danger-text');
        // Loader Text zurücksetzen, falls er beim letzten Mal auf "Fehler" stand
        this.modalLoader.innerText = 'Wird generiert...';
        this.modal.classList.add('c-modal--open');

        // Die QR-Code API url-encoded aufrufen
        const encodedUrl = encodeURIComponent(url);

        // TODO ARCHITEKTUR NOTIZ (Kritisch):
        // Das Senden von Gutschein-URLs an api.qrserver.com speichert diese Klartext-URLs
        // in fremden Server-Logs. Dies ist ein massives Sicherheits- und Datenschutzrisiko!
        // Lösung für das Backend-Team: Ersetzt diese URL durch einen lokalen PHP-Endpoint, z.B.:
        // const qrUrl = `${window.KGA_CONFIG.baseUrl}api/generate_qr?data=${encodedUrl}`;
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=${encodedUrl}`;

        // Wir blenden das Bild erst ein, wenn die externe API es fertig gerendert hat
        this.modalImg.onload = () => {
            this.modalLoader.classList.add('u-hidden');
            this.modalImg.classList.remove('u-hidden');
        };

        // Fehlerbehandlung, falls die externe API offline oder geblockt ist!
        this.modalImg.onerror = () => {
            this.modalImg.classList.add('u-hidden');
            this.modalLoader.classList.remove('u-hidden');
            this.modalLoader.innerText = 'Fehler: QR-Code API nicht erreichbar.';
            this.modalLoader.classList.add('is-danger-text');
        };

        this.modalImg.src = qrUrl;
    }

    closeQr() {
        if (!this.modal) return;
        this.modal.classList.remove('c-modal--open');
        this.modalImg.src = ''; // Leeren, damit beim nächsten Mal der Loader wieder erscheint
        // Loader-Style sicherheitshalber resetten
        this.modalLoader.classList.remove('is-danger-text');
    }

    async copyLink(url, element) {
        // Lock-State verhindert permanente Zerstörung des Button-Texts durch Spam-Klicks
        if (element.dataset.isCopying) return;
        element.dataset.isCopying = 'true';

        const originalHtml = element.innerHTML;

        const successAction = () => {
            element.innerText = 'Kopiert! ✓';
            // FIX: Farbänderung als Klasse toggeln
            element.classList.add('is-success-text');
            notifier.show('Gutschein-Link in die Zwischenablage kopiert!', 'success');

            // Reset nach 2 Sekunden
            setTimeout(() => {
                element.innerHTML = originalHtml;
                element.classList.remove('is-success-text');
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
}
