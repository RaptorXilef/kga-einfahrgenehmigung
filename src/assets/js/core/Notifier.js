/**
 * Zentraler Notification-Service (Toasts) als Singleton.
 * Überschreibt bestehende Toasts sofort, um schnelle Feedbacks
 * ohne blockierende Queues oder hängende CSS-Transitions zu garantieren.
 */

class NotifierService {
    constructor() {
        this.baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        this.currentToast = null;
        this.hideTimeout = null;
    }

    show(message, type = 'success') {
        // Wenn bereits ein Toast angezeigt wird, diesen sofort restlos entfernen
        if (this.currentToast) {
            this.currentToast.remove();
            clearTimeout(this.hideTimeout);
            this.currentToast = null;
        }

        const toast = document.createElement('div');
        toast.className = `c-toast c-toast--${type}`;

        // A11Y: Screenreader-Fokus & Live-Announcements
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        toast.setAttribute('aria-atomic', 'true');

        if (type === 'success' || type === 'error') {
            const iconName = type === 'success' ? 'success.webp' : 'error.webp';
            const icon = document.createElement('img');
            icon.src = `${this.baseUrl}assets/img/icons/${iconName}`;
            icon.className = 'c-icon c-toast__icon';
            icon.alt = '';
            // Decorative Icons müssen für Screenreader versteckt werden
            icon.setAttribute('aria-hidden', 'true');
            toast.appendChild(icon);
        }

        // Sicheres Einfügen der Nachricht als Textknoten zur XSS-Prävention
        const msgContainer = document.createElement('span');
        msgContainer.className = 'c-toast__msg js-toast-msg';
        msgContainer.textContent = message;
        toast.appendChild(msgContainer);

        document.body.appendChild(toast);
        this.currentToast = toast;

        // Garantiertes Slide-Out nach 5 Sekunden Sichtbarkeit
        this.hideTimeout = setTimeout(() => {
            // Nur ausführen, wenn dieser Toast noch der aktive ist
            if (this.currentToast === toast) {
                toast.classList.add('is-hidden');

                // Robustes DOM-Cleanup per Timeout (Kein unzuverlässiges transitionend-Event!)
                setTimeout(() => {
                    if (this.currentToast === toast) {
                        toast.remove();
                        this.currentToast = null;
                    }
                }, 600); // Entspricht der Zeit der CSS Transition
            }
        }, 5000);
    }
}

// Als Singleton exportieren
export const notifier = new NotifierService();
