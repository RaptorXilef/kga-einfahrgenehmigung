/**
 * Zentraler Notification-Service (Toasts) als Singleton.
 * Ersetzt verstreute Inline-Toast-Logiken durch eine einheitliche, moderne API.
 */

class NotifierService {
    constructor() {
        this.baseUrl = window.KGA_CONFIG?.baseUrl || '/';
        // Speichere Timer-IDs, um Memory Leaks durch Zombie-Closures zu verhindern
        this.hideTimeout = null;
        this.removeTimeout = null;
    }

    show(message, type = 'success') {
        // Alte Timer stoppen!
        if (this.hideTimeout) clearTimeout(this.hideTimeout);
        if (this.removeTimeout) clearTimeout(this.removeTimeout);

        // Alte Toasts entfernen, falls noch sichtbar
        const existingToast = document.querySelector('.c-toast');
        if (existingToast) existingToast.remove();

        const toast = document.createElement('div');
        // FIX: Saubere BEM Modifikatoren statt Inline-Styles
        toast.className = `c-toast c-toast--${type}`;

        if (type === 'success' || type === 'error') {
            const iconName = type === 'success' ? 'status-success.webp' : 'status-denied.webp';
            const icon = document.createElement('img');
            icon.src = `${this.baseUrl}assets/img/icons/${iconName}`;
            icon.className = 'c-icon c-toast__icon';
            icon.alt = '';
            toast.appendChild(icon);
        }

        // Sicheres Einfügen der Nachricht als Text!
        const msgContainer = document.createElement('span');
        msgContainer.className = 'c-toast__msg js-toast-msg';
        msgContainer.textContent = message;
        toast.appendChild(msgContainer);

        document.body.appendChild(toast);

        // Slide-Out Animation nach 3 Sekunden
        this.hideTimeout = setTimeout(() => {
            toast.classList.add('is-hidden');
            this.removeTimeout = setTimeout(() => toast.remove(), 400); // Gematcht auf CSS-Transition
        }, 3000);
    }
}

// Als Singleton exportieren
export const notifier = new NotifierService();
