/**
 * Zentraler Notification-Service (Toasts) als Singleton.
 * Implementiert eine Warteschlange (Queue), um abgerissene CSS-Transitions
 * und stumme Screenreader-Announcements bei schnellen Aufrufen zu verhindern.
 */

class NotifierService {
    constructor() {
        this.baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        this.queue = [];
        this.isShowing = false;
    }

    show(message, type = 'success') {
        // Nachricht in die Warteschlange einreihen
        this.queue.push({ message, type });

        // Wenn gerade kein Toast angezeigt wird, Queue-Verarbeitung starten
        if (!this.isShowing) {
            this.#processQueue();
        }
    }

    #processQueue() {
        // Abbruch, wenn die Warteschlange leer ist
        if (this.queue.length === 0) {
            this.isShowing = false;
            return;
        }

        this.isShowing = true;

        // Ältestes Element aus der Queue holen
        const { message, type } = this.queue.shift();

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

        // Sicheres Einfügen der Nachricht als Textknoten zur XSS-Prävention!
        const msgContainer = document.createElement('span');
        msgContainer.className = 'c-toast__msg js-toast-msg';
        msgContainer.textContent = message;
        toast.appendChild(msgContainer);

        document.body.appendChild(toast);

        // Slide-Out Animation nach 3 Sekunden garantierter Sichtbarkeit
        setTimeout(() => {
            toast.classList.add('is-hidden');

            // Auf das Ende der CSS-Transition warten (400ms matcht CSS), dann Knoten restlos entfernen
            setTimeout(() => {
                toast.remove();
                // Rekursiv den nächsten Toast in der Queue aufrufen
                this.#processQueue();
            }, 400);
        }, 3000);
    }
}

// Als Singleton exportieren
export const notifier = new NotifierService();
